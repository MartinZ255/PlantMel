<?php

namespace Tests\Feature\Recipes;

use App\Models\Category;
use App\Models\RatingDimension;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Regressionstest zu F-22 (behoben in Slice 1b): Eingaben, die früher zu 500
 * statt zu einer sauberen Antwort führten.
 * - `?search[]=a` -> TypeError in trim(). Jetzt: ungültige Formen werden in
 *   recipes.index still ignoriert (200), gültige Filter bleiben wirksam.
 * - `ratings.*`-Schlüssel wurden nicht gegen rating_dimensions geprüft
 *   -> Fremdschlüssel-Verletzung. Jetzt: Validierungsfehler auf `ratings`.
 * - Rezept-Strings haben `max`-Grenzen passend zu den Spalten.
 */
#[Group('F-22')]
class RecipeInputValidationTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function malformedIndexQueries(): array
    {
        return [
            'search als Array' => ['search[]=a'],
            'search als verschachteltes Array' => ['search[a][]=b'],
            'tags verschachtelt' => ['tags[0][]=Vegan'],
            'includeIngredients verschachtelt' => ['includeIngredients[x][y]=Tofu'],
            'excludeIngredients verschachtelt' => ['excludeIngredients[][]=Tofu'],
        ];
    }

    #[DataProvider('malformedIndexQueries')]
    public function test_malformed_index_query_is_ignored_instead_of_server_error(string $query): void
    {
        Recipe::factory()->create(['title' => 'Linsen-Bolognese']);

        $this->get('/rezepte?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('recipes/Index')
                ->has('recipes', 1)
                ->where('filters.search', '')
                ->where('filters.tags', [])
                ->where('filters.includeIngredients', [])
                ->where('filters.excludeIngredients', [])
            );
    }

    public function test_valid_filters_still_apply_next_to_ignored_nested_values(): void
    {
        $vegan = Category::factory()->create(['name' => 'Vegan']);
        $match = Recipe::factory()->create(['title' => 'Linsen-Bolognese']);
        $match->categories()->attach($vegan);
        Recipe::factory()->create(['title' => 'Linsen-Suppe']);

        $this->get('/rezepte?search=Linsen&tags[]=Vegan&tags[1][]=kaputt')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('recipes', 1)
                ->where('recipes.0.id', $match->id)
                ->where('filters.search', 'Linsen')
                ->where('filters.tags', ['Vegan'])
            );
    }

    public function test_unknown_rating_dimension_is_a_validation_error_on_store(): void
    {
        $this->actingAsHost();

        $response = $this->from(route('recipes.create'))
            ->post(route('recipes.store'), $this->recipePayload(['ratings' => [999999 => 3]]));

        $response->assertRedirect(route('recipes.create'));
        $response->assertSessionHasErrors('ratings');
        $this->assertSame(0, Recipe::count());
    }

    public function test_unknown_rating_dimension_is_a_validation_error_on_update(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create(['title' => 'Vorher']);
        $known = RatingDimension::factory()->create();

        $this->from(route('recipes.edit', $recipe))
            ->post(route('recipes.update', $recipe), $this->recipePayload([
                'name' => 'Nachher',
                'ratings' => [$known->id => 4, 999999 => 3],
            ]))
            ->assertRedirect(route('recipes.edit', $recipe))
            ->assertSessionHasErrors('ratings');

        $this->assertSame('Vorher', $recipe->fresh()->title);
    }

    public function test_known_rating_dimensions_are_still_saved(): void
    {
        $this->actingAsHost();
        $taste = RatingDimension::factory()->create();
        $effort = RatingDimension::factory()->create();

        $this->post(route('recipes.store'), $this->recipePayload([
            'ratings' => [$taste->id => 5, $effort->id => null],
        ]))->assertSessionHasNoErrors();

        $recipe = Recipe::sole();
        $this->assertDatabaseHas('recipe_ratings', ['recipe_id' => $recipe->id, 'dimension_id' => $taste->id, 'value' => 5]);
        $this->assertDatabaseCount('recipe_ratings', 1);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function tooLongRecipeStrings(): array
    {
        return [
            'Zutatenname > 255 (ingredients.name)' => [['ingredients' => [['amount' => '1', 'name' => str_repeat('a', 256)]]], 'ingredients.0.name'],
            'Menge > 255 (recipe_ingredients.note)' => [['ingredients' => [['amount' => str_repeat('1', 256), 'name' => 'Salz']]], 'ingredients.0.amount'],
            'Tag > 255' => [['tags' => [str_repeat('t', 256)]], 'tags.0'],
            'Beschreibung > 65535 (TEXT)' => [['description' => str_repeat('d', 65536)], 'description'],
            'Schritt > 65535 (TEXT)' => [['steps' => [str_repeat('s', 65536)]], 'steps.0'],
            'Notizen > 65535 (TEXT)' => [['notes' => str_repeat('n', 65536)], 'notes'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('tooLongRecipeStrings')]
    public function test_recipe_strings_longer_than_column_are_rejected(array $overrides, string $errorKey): void
    {
        $this->actingAsHost();

        $this->from(route('recipes.create'))
            ->post(route('recipes.store'), $this->recipePayload($overrides))
            ->assertSessionHasErrors($errorKey);

        $this->assertSame(0, Recipe::count());
    }

    public function test_recipe_strings_at_column_limit_are_accepted(): void
    {
        $this->actingAsHost();

        $this->post(route('recipes.store'), $this->recipePayload([
            'ingredients' => [['amount' => str_repeat('1', 255), 'name' => str_repeat('a', 255)]],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Recipe::count());
    }
}
