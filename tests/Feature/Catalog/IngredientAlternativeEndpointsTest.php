<?php

namespace Tests\Feature\Catalog;

use App\Models\Ingredient;
use App\Models\IngredientAlternative;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Charakterisierung IngredientAlternativeController (JSON, Host).
 */
class IngredientAlternativeEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_alternatives_of_recipe_and_ingredient_only(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();
        $base = Ingredient::factory()->create();
        $alt = Ingredient::factory()->create(['name' => 'Hafermilch']);

        $match = IngredientAlternative::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $base->id,
            'alternative_ingredient_id' => $alt->id,
        ]);
        // Gleiche Basiszutat, anderes Rezept -> nicht enthalten
        IngredientAlternative::factory()->create(['ingredient_id' => $base->id]);
        // Gleiches Rezept, andere Basiszutat -> nicht enthalten
        IngredientAlternative::factory()->create(['recipe_id' => $recipe->id]);

        $this->getJson(route('ingredientAlternatives.index', [$recipe, $base]))
            ->assertOk()
            ->assertExactJson([[
                'id' => $match->id,
                'ingredient_id' => $base->id,
                'alternative_ingredient_id' => $alt->id,
                'alternative' => ['id' => $alt->id, 'name' => 'Hafermilch'],
            ]]);
    }

    public function test_index_returns_empty_array(): void
    {
        $this->actingAsHost();

        $this->getJson(route('ingredientAlternatives.index', [Recipe::factory()->create(), Ingredient::factory()->create()]))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_store_creates_alternative_and_returns_201(): void
    {
        $this->actingAsHost();
        $usage = RecipeIngredient::factory()->create();
        $alt = Ingredient::factory()->create(['name' => 'Sojamilch']);

        $response = $this->postJson(
            route('ingredientAlternatives.store', [$usage->recipe_id, $usage->ingredient_id]),
            ['alternative_ingredient_id' => $alt->id],
        );

        $created = IngredientAlternative::sole();
        $response->assertCreated()->assertExactJson([
            'id' => $created->id,
            'ingredient_id' => $usage->ingredient_id,
            'alternative_ingredient_id' => $alt->id,
            'alternative' => ['id' => $alt->id, 'name' => 'Sojamilch'],
        ]);
        $this->assertSame($usage->recipe_id, $created->recipe_id);
        $this->assertNull($created->note);
    }

    public function test_store_validation(): void
    {
        $this->actingAsHost();
        $usage = RecipeIngredient::factory()->create();
        $url = route('ingredientAlternatives.store', [$usage->recipe_id, $usage->ingredient_id]);

        $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('alternative_ingredient_id');
        $this->postJson($url, ['alternative_ingredient_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('alternative_ingredient_id');
        $this->postJson($url, ['alternative_ingredient_id' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors('alternative_ingredient_id');

        $this->assertDatabaseCount('ingredient_alternatives', 0);
    }

    /**
     * Ist-Zustand: Es wird nicht geprüft, ob die Basiszutat überhaupt zum
     * Rezept gehört – die Alternative wird trotzdem angelegt (201).
     */
    public function test_store_accepts_ingredient_that_is_not_part_of_recipe(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();
        $notInRecipe = Ingredient::factory()->create();
        $alt = Ingredient::factory()->create();

        $this->assertFalse($recipe->recipeIngredients()->where('ingredient_id', $notInRecipe->id)->exists());

        $this->postJson(
            route('ingredientAlternatives.store', [$recipe, $notInRecipe]),
            ['alternative_ingredient_id' => $alt->id],
        )->assertCreated();

        $this->assertDatabaseHas('ingredient_alternatives', [
            'recipe_id' => $recipe->id,
            'ingredient_id' => $notInRecipe->id,
            'alternative_ingredient_id' => $alt->id,
        ]);
    }

    /**
     * Ist-Zustand: Auch die Zutat selbst wird als ihre eigene Alternative akzeptiert.
     */
    public function test_store_accepts_ingredient_as_its_own_alternative(): void
    {
        $this->actingAsHost();
        $usage = RecipeIngredient::factory()->create();

        $this->postJson(
            route('ingredientAlternatives.store', [$usage->recipe_id, $usage->ingredient_id]),
            ['alternative_ingredient_id' => $usage->ingredient_id],
        )->assertCreated();

        $this->assertDatabaseCount('ingredient_alternatives', 1);
    }

    public function test_destroy_deletes_alternative_only(): void
    {
        $this->actingAsHost();
        $alternative = IngredientAlternative::factory()->create();

        $this->deleteJson(route('ingredientAlternatives.destroy', $alternative))
            ->assertOk()
            ->assertExactJson(['status' => 'deleted']);

        $this->assertModelMissing($alternative);
        $this->assertDatabaseCount('ingredients', 2);
        $this->assertDatabaseCount('recipes', 1);
    }
}
