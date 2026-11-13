<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Charakterisierung CategoryController (JSON-Endpunkte für Hosts).
 */
class CategoryEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_category_and_returns_201(): void
    {
        $this->actingAsHost();

        $response = $this->postJson(route('categories.store'), ['name' => 'Vegan']);

        $category = Category::sole();
        $response->assertCreated()
            ->assertExactJson(['id' => $category->id, 'name' => 'Vegan']);
        $this->assertNull($category->description);
    }

    public function test_store_rejects_duplicate_and_missing_name(): void
    {
        $this->actingAsHost();
        Category::factory()->create(['name' => 'Vegan']);

        $this->postJson(route('categories.store'), ['name' => 'Vegan'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->postJson(route('categories.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->postJson(route('categories.store'), ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('categories', 1);
    }

    public function test_update_renames_category(): void
    {
        $this->actingAsHost();
        $category = Category::factory()->create(['name' => 'Alt']);

        $this->putJson(route('categories.update', $category), ['name' => 'Neu'])
            ->assertOk()
            ->assertExactJson(['id' => $category->id, 'name' => 'Neu']);

        $this->assertSame('Neu', $category->fresh()->name);
    }

    public function test_update_allows_same_name_but_rejects_other_existing_name(): void
    {
        $this->actingAsHost();
        $category = Category::factory()->create(['name' => 'Eins']);
        Category::factory()->create(['name' => 'Zwei']);

        $this->putJson(route('categories.update', $category), ['name' => 'Eins'])
            ->assertOk();

        $this->putJson(route('categories.update', $category), ['name' => 'Zwei'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame('Eins', $category->fresh()->name);
    }

    /**
     * Ist-Zustand F-07: Löschen löst die Kategorie ohne Rückfrage von allen
     * Rezepten; die Rezepte selbst bleiben bestehen.
     */
    public function test_destroy_detaches_category_from_recipes_f07(): void
    {
        $this->actingAsHost();
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        $recipes = Recipe::factory()->count(3)->create();
        $category->recipes()->attach($recipes);
        $otherCategory->recipes()->attach($recipes->first());

        $this->assertDatabaseCount('recipe_categories', 4);

        $this->deleteJson(route('categories.destroy', $category))
            ->assertOk()
            ->assertExactJson(['status' => 'deleted']);

        $this->assertModelMissing($category);
        $this->assertDatabaseCount('recipe_categories', 1);
        $this->assertDatabaseHas('recipe_categories', [
            'recipe_id' => $recipes->first()->id,
            'category_id' => $otherCategory->id,
        ]);
        $this->assertDatabaseCount('recipes', 3);
    }

    public function test_unknown_category_returns_404(): void
    {
        $this->actingAsHost();

        $this->putJson(route('categories.update', 999999), ['name' => 'x'])->assertNotFound();
        $this->deleteJson(route('categories.destroy', 999999))->assertNotFound();
    }
}
