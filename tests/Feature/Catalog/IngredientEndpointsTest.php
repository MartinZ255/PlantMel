<?php

namespace Tests\Feature\Catalog;

use App\Models\Ingredient;
use App\Models\RecipeIngredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Charakterisierung IngredientController store/update/destroy (JSON, Host).
 */
class IngredientEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_ingredient_and_returns_201(): void
    {
        $this->actingAsHost();

        $response = $this->postJson(route('ingredients.store'), [
            'name' => 'Tofu',
            'description' => 'Sojaquark',
        ]);

        $ingredient = Ingredient::sole();
        $response->assertCreated()->assertExactJson([
            'id' => $ingredient->id,
            'name' => 'Tofu',
            'description' => 'Sojaquark',
        ]);
    }

    public function test_store_without_description(): void
    {
        $this->actingAsHost();

        $this->postJson(route('ingredients.store'), ['name' => 'Salz'])
            ->assertCreated()
            ->assertJsonPath('description', null);
    }

    public function test_store_validation(): void
    {
        $this->actingAsHost();
        Ingredient::factory()->create(['name' => 'Tofu']);

        $this->postJson(route('ingredients.store'), ['name' => 'Tofu'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('ingredients.store'), [])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('ingredients.store'), ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('ingredients.store'), ['name' => 'Neu', 'description' => ['x']])
            ->assertUnprocessable()->assertJsonValidationErrors('description');

        $this->assertDatabaseCount('ingredients', 1);
    }

    public function test_update_changes_name_and_description(): void
    {
        $this->actingAsHost();
        $ingredient = Ingredient::factory()->create(['name' => 'Alt', 'description' => 'd']);
        Ingredient::factory()->create(['name' => 'Belegt']);

        $this->putJson(route('ingredients.update', $ingredient), ['name' => 'Alt'])
            ->assertOk()
            // description fehlt im Request -> wird per validated() nicht gesetzt
            ->assertExactJson(['id' => $ingredient->id, 'name' => 'Alt', 'description' => 'd']);

        $this->putJson(route('ingredients.update', $ingredient), ['name' => 'Neu', 'description' => null])
            ->assertOk()
            ->assertExactJson(['id' => $ingredient->id, 'name' => 'Neu', 'description' => null]);

        $this->putJson(route('ingredients.update', $ingredient), ['name' => 'Belegt'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->assertSame('Neu', $ingredient->fresh()->name);
    }

    public function test_destroy_unused_ingredient(): void
    {
        $this->actingAsHost();
        $ingredient = Ingredient::factory()->create();

        $this->deleteJson(route('ingredients.destroy', $ingredient))
            ->assertOk()
            ->assertExactJson(['status' => 'deleted']);

        $this->assertModelMissing($ingredient);
    }

    /**
     * Ist-Zustand: Ist die Zutat nur Basiszutat einer Alternative (nicht in
     * recipe_ingredients), wird sie gelöscht und die Alternativen verschwinden
     * per cascadeOnDelete still mit. (Als Alternative genutzt -> 500, F-18.)
     */
    public function test_destroy_base_ingredient_of_alternative_cascades_silently(): void
    {
        $this->actingAsHost();
        $alternative = \App\Models\IngredientAlternative::factory()->create();
        $base = $alternative->ingredient;

        $this->deleteJson(route('ingredients.destroy', $base))
            ->assertOk()
            ->assertExactJson(['status' => 'deleted']);

        $this->assertModelMissing($base);
        $this->assertModelMissing($alternative);
        $this->assertModelExists($alternative->alternativeIngredient);
    }

    public function test_destroy_ingredient_used_in_recipe_returns_422(): void
    {
        $this->actingAsHost();
        $usage = RecipeIngredient::factory()->create();
        $ingredient = $usage->ingredient;

        $this->deleteJson(route('ingredients.destroy', $ingredient))
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Die Zutat wird in mindestens einem Rezept verwendet und kann nicht gelöscht werden.',
            ]);

        $this->assertModelExists($ingredient);
        $this->assertModelExists($usage);
    }
}
