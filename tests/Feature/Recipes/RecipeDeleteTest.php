<?php

namespace Tests\Feature\Recipes;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Comment;
use App\Models\Ingredient;
use App\Models\IngredientAlternative;
use App\Models\RatingDimension;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Models\RecipeRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Charakterisierung von RecipeController::delete (DELETE /rezept/{recipe}) als Host.
 */
class RecipeDeleteTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    public function test_delete_removes_recipe_and_dependent_rows_and_redirects_to_index(): void
    {
        $host = $this->actingAsHost();
        $category = Category::factory()->create();
        $dimension = RatingDimension::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $host->id]);

        $recipe = Recipe::factory()->create();
        $tofu = $this->attachIngredient($recipe, 'Tofu', '200 g');
        $seitan = Ingredient::factory()->create(['name' => 'Seitan']);
        IngredientAlternative::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $tofu->id,
            'alternative_ingredient_id' => $seitan->id,
        ]);
        $recipe->categories()->attach($category);
        RecipeRating::factory()->create(['recipe_id' => $recipe->id, 'dimension_id' => $dimension->id]);
        RecipeImage::factory()->create(['recipe_id' => $recipe->id]);
        Comment::factory()->create(['recipe_id' => $recipe->id]);
        $collection->recipes()->attach($recipe);

        // Ein zweites Rezept darf unberührt bleiben.
        $other = Recipe::factory()->create();
        $this->attachIngredient($other, 'Tofu', '50 g');
        $collection->recipes()->attach($other);

        $response = $this->delete(route('recipes.delete', $recipe));

        $response->assertRedirect(route('recipes.index'));
        $response->assertSessionHas('success', 'Rezept wurde gelöscht.');

        $this->assertModelMissing($recipe);
        $this->assertDatabaseMissing('recipe_ingredients', ['recipe_id' => $recipe->id]);
        $this->assertDatabaseMissing('ingredient_alternatives', ['recipe_id' => $recipe->id]);
        $this->assertDatabaseMissing('recipe_categories', ['recipe_id' => $recipe->id]);
        $this->assertDatabaseMissing('recipe_ratings', ['recipe_id' => $recipe->id]);
        $this->assertDatabaseMissing('recipe_images', ['recipe_id' => $recipe->id]);
        $this->assertDatabaseMissing('comments', ['recipe_id' => $recipe->id]);
        $this->assertDatabaseMissing('collection_recipes', ['recipe_id' => $recipe->id]);

        // Stammdaten und andere Rezepte bleiben.
        $this->assertModelExists($other);
        $this->assertModelExists($collection);
        $this->assertModelExists($category);
        $this->assertModelExists($dimension);
        $this->assertModelExists($tofu);
        $this->assertModelExists($seitan);
        $this->assertDatabaseHas('recipe_ingredients', ['recipe_id' => $other->id]);
        $this->assertDatabaseHas('collection_recipes', ['collection_id' => $collection->id, 'recipe_id' => $other->id]);
    }

    public function test_delete_unknown_recipe_returns_404(): void
    {
        $this->actingAsHost();

        $this->delete('/rezept/999999')->assertNotFound();
    }
}
