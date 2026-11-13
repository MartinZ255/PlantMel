<?php

namespace Tests\KnownDefects\Recipes;

use App\Models\Ingredient;
use App\Models\IngredientAlternative;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Bekannter Defekt F-13: RecipeController::update() validiert
 * ingredients.*.alternativeIngredientIds, speichert sie aber nie
 * (app/Http/Controllers/RecipeController.php:605-606 vs. :657-674).
 *
 * Sollverhalten: Nach dem Update entsprechen die Alternativen exakt dem Gesendeten;
 * Alternativen entfernter Zutaten sind gelöscht.
 * Gegenpart (Ist-Verhalten): Tests\Feature\Recipes\RecipeCurrentDefectBehaviourTest (…_f13).
 */
#[Group('known-defect')]
#[Group('F-13')]
class RecipeUpdateAlternativesTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    public function test_update_stores_sent_alternative_ids(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();
        $this->attachIngredient($recipe, 'Tofu', '200 g');
        $tempeh = Ingredient::factory()->create(['name' => 'Tempeh']);
        $jackfruit = Ingredient::factory()->create(['name' => 'Jackfruit']);

        $this->post(route('recipes.update', $recipe), $this->recipePayload([
            'ingredients' => [
                ['amount' => '200 g', 'name' => 'Tofu', 'alternativeIngredientIds' => [$tempeh->id]],
                ['amount' => '1', 'name' => 'Neue Zutat', 'alternativeIngredientIds' => [$jackfruit->id]],
            ],
        ]))->assertSessionHasNoErrors();

        $tofuId = Ingredient::where('name', 'Tofu')->value('id');
        $neuId = Ingredient::where('name', 'Neue Zutat')->value('id');

        $this->assertDatabaseHas('ingredient_alternatives', [
            'recipe_id' => $recipe->id,
            'ingredient_id' => $tofuId,
            'alternative_ingredient_id' => $tempeh->id,
        ]);
        $this->assertDatabaseHas('ingredient_alternatives', [
            'recipe_id' => $recipe->id,
            'ingredient_id' => $neuId,
            'alternative_ingredient_id' => $jackfruit->id,
        ]);

        $form = $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe'];
        $this->assertSame([$tempeh->id], $form['ingredients'][0]['alternativeIngredientIds']);
        $this->assertSame([$jackfruit->id], $form['ingredients'][1]['alternativeIngredientIds']);
    }

    public function test_update_removes_alternatives_of_removed_ingredients(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();
        $tofu = $this->attachIngredient($recipe, 'Tofu', '200 g');
        $seitan = Ingredient::factory()->create(['name' => 'Seitan']);
        IngredientAlternative::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $tofu->id,
            'alternative_ingredient_id' => $seitan->id,
        ]);

        $this->post(route('recipes.update', $recipe), $this->recipePayload([
            'ingredients' => [['amount' => '1', 'name' => 'Zwiebel']],
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('ingredient_alternatives', [
            'recipe_id' => $recipe->id,
            'ingredient_id' => $tofu->id,
        ]);
    }
}
