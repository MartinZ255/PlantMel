<?php

namespace Tests\Feature\Recipes;

use App\Models\Ingredient;
use App\Models\IngredientAlternative;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Regressionstests zu F-13: store() und update() speichern
 * ingredients.*.alternativeIngredientIds identisch; nach dem Update entsprechen
 * die ingredient_alternatives des Rezepts exakt dem Payload.
 *
 * Vertrag: Fehlt `alternativeIngredientIds` in einer Zeile (FormData überträgt
 * leere Arrays nicht), hat die Zutat keine Alternativen. Doppelte IDs und die
 * Zutat als eigene Alternative werden still verworfen (kein 500, kein 422).
 */
#[Group('F-13')]
class RecipeAlternativesSyncTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    private Recipe $recipe;

    private Ingredient $tofu;

    private Ingredient $seitan;

    private Ingredient $tempeh;

    private Ingredient $jackfruit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsHost();
        $this->recipe = Recipe::factory()->create();
        $this->tofu = $this->attachIngredient($this->recipe, 'Tofu', '200 g');
        $this->seitan = Ingredient::factory()->create(['name' => 'Seitan']);
        $this->tempeh = Ingredient::factory()->create(['name' => 'Tempeh']);
        $this->jackfruit = Ingredient::factory()->create(['name' => 'Jackfruit']);
        IngredientAlternative::factory()->create([
            'recipe_id' => $this->recipe->id,
            'ingredient_id' => $this->tofu->id,
            'alternative_ingredient_id' => $this->seitan->id,
        ]);
    }

    /**
     * @return list<array{0: string, 1: string}> [Basiszutat, Alternative] in DB-Reihenfolge
     */
    private function alternativesOf(Recipe $recipe): array
    {
        return IngredientAlternative::query()
            ->where('recipe_id', $recipe->id)
            ->orderBy('id')
            ->with(['ingredient', 'alternativeIngredient'])
            ->get()
            ->map(fn (IngredientAlternative $a) => [$a->ingredient->name, $a->alternativeIngredient->name])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $ingredients
     */
    private function update(array $ingredients): void
    {
        $this->post(route('recipes.update', $this->recipe), $this->recipePayload([
            'ingredients' => $ingredients,
        ]))->assertSessionHasNoErrors()->assertRedirect(route('recipes.show', $this->recipe));
    }

    public function test_update_adds_and_removes_alternatives_to_match_payload(): void
    {
        $this->update([
            ['amount' => '200 g', 'name' => 'Tofu', 'alternativeIngredientIds' => [$this->tempeh->id, $this->jackfruit->id]],
        ]);

        $this->assertSame([['Tofu', 'Tempeh'], ['Tofu', 'Jackfruit']], $this->alternativesOf($this->recipe));
    }

    public function test_update_keeps_unchanged_alternatives(): void
    {
        $this->update([
            ['amount' => '200 g', 'name' => 'Tofu', 'alternativeIngredientIds' => [$this->seitan->id]],
        ]);

        $this->assertSame([['Tofu', 'Seitan']], $this->alternativesOf($this->recipe));
    }

    public function test_missing_or_empty_alternative_list_removes_alternatives(): void
    {
        $this->update([['amount' => '200 g', 'name' => 'Tofu']]);
        $this->assertSame([], $this->alternativesOf($this->recipe));

        $this->update([['amount' => '200 g', 'name' => 'Tofu', 'alternativeIngredientIds' => [$this->seitan->id]]]);
        $this->update([['amount' => '200 g', 'name' => 'Tofu', 'alternativeIngredientIds' => []]]);
        $this->assertSame([], $this->alternativesOf($this->recipe));
    }

    public function test_alternatives_of_removed_ingredient_do_not_come_back(): void
    {
        $this->update([['amount' => '1', 'name' => 'Zwiebel']]);
        $this->assertSame([], $this->alternativesOf($this->recipe));

        // Früher tauchte die verwaiste Alternative beim erneuten Hinzufügen wieder auf.
        $this->update([['amount' => '1', 'name' => 'Tofu']]);
        $form = $this->pageProps($this->get(route('recipes.edit', $this->recipe)))['recipe'];
        $this->assertSame([], $form['ingredients'][0]['alternativeIngredientIds']);
    }

    public function test_duplicate_ids_and_self_alternative_are_dropped_on_update(): void
    {
        $this->update([
            ['amount' => '200 g', 'name' => 'Tofu', 'alternativeIngredientIds' => [
                $this->tempeh->id, $this->tempeh->id, $this->tofu->id, (string) $this->tempeh->id,
            ]],
        ]);

        $this->assertSame([['Tofu', 'Tempeh']], $this->alternativesOf($this->recipe));
    }

    public function test_same_ingredient_in_two_rows_merges_alternatives_without_error(): void
    {
        $this->update([
            ['amount' => '100 g', 'name' => 'Tofu', 'alternativeIngredientIds' => [$this->tempeh->id]],
            ['amount' => '50 g', 'name' => 'Tofu', 'alternativeIngredientIds' => [$this->tempeh->id, $this->seitan->id]],
        ]);

        $this->assertSame([['Tofu', 'Tempeh'], ['Tofu', 'Seitan']], $this->alternativesOf($this->recipe));
    }

    public function test_store_drops_duplicates_and_self_alternative_like_update(): void
    {
        $this->post(route('recipes.store'), $this->recipePayload([
            'name' => 'Neues Rezept',
            'ingredients' => [
                ['amount' => '1', 'name' => 'Tofu', 'alternativeIngredientIds' => [
                    $this->seitan->id, $this->seitan->id, $this->tofu->id,
                ]],
                ['amount' => '1', 'name' => 'Reis'],
            ],
        ]))->assertSessionHasNoErrors();

        $created = Recipe::where('title', 'Neues Rezept')->sole();
        $this->assertSame([['Tofu', 'Seitan']], $this->alternativesOf($created));
    }

    public function test_update_does_not_touch_alternatives_of_other_recipes(): void
    {
        $other = Recipe::factory()->create();
        $this->attachIngredient($other, 'Tofu', '1');
        IngredientAlternative::factory()->create([
            'recipe_id' => $other->id,
            'ingredient_id' => $this->tofu->id,
            'alternative_ingredient_id' => $this->jackfruit->id,
        ]);

        $this->update([['amount' => '1', 'name' => 'Zwiebel']]);

        $this->assertSame([['Tofu', 'Jackfruit']], $this->alternativesOf($other));
    }

    public function test_edit_props_reflect_saved_alternatives(): void
    {
        $this->update([
            ['amount' => '200 g', 'name' => 'Tofu', 'alternativeIngredientIds' => [$this->jackfruit->id, $this->tempeh->id]],
            ['amount' => '1', 'name' => 'Reis'],
        ]);

        // Reihenfolge der Alternativen ist nicht festgelegt (edit/show sortieren nicht)
        $form = $this->pageProps($this->get(route('recipes.edit', $this->recipe)))['recipe'];
        $this->assertEqualsCanonicalizing([$this->jackfruit->id, $this->tempeh->id], $form['ingredients'][0]['alternativeIngredientIds']);
        $this->assertSame([], $form['ingredients'][1]['alternativeIngredientIds']);

        $show = $this->pageProps($this->get(route('recipes.show', $this->recipe)))['recipe'];
        $this->assertEqualsCanonicalizing(['Jackfruit', 'Tempeh'], $show['ingredients'][0]['alternatives']);
    }

    public function test_unknown_alternative_id_is_still_rejected(): void
    {
        $this->post(route('recipes.update', $this->recipe), $this->recipePayload([
            'ingredients' => [['amount' => '1', 'name' => 'Tofu', 'alternativeIngredientIds' => [999999]]],
        ]))->assertSessionHasErrors('ingredients.0.alternativeIngredientIds.0');

        // Nichts geändert
        $this->assertSame([['Tofu', 'Seitan']], $this->alternativesOf($this->recipe));
    }
}
