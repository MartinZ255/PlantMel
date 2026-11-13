<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\RatingDimension;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Charakterisierung: Bewertungsdimensionen-API, Zutatensuche-Seite und
 * "Meine Zutaten"-Seite (Ist-Zustand).
 */
class CatalogPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_rating_dimensions_api_is_public_and_sorted_by_name(): void
    {
        $b = RatingDimension::factory()->create(['name' => 'Geschmack', 'description' => 'Wie schmeckt es?']);
        $a = RatingDimension::factory()->create(['name' => 'Aufwand', 'description' => null]);

        $response = $this->getJson('/api/rating-dimensions')->assertOk();

        $response->assertJsonCount(2)
            ->assertJsonStructure([['id', 'name', 'description', 'created_at', 'updated_at']])
            ->assertJsonPath('0.id', $a->id)
            ->assertJsonPath('0.name', 'Aufwand')
            ->assertJsonPath('0.description', null)
            ->assertJsonPath('1.id', $b->id)
            ->assertJsonPath('1.description', 'Wie schmeckt es?');

        // Exakt diese Schlüssel (komplettes Modell, kein Resource-Mapping).
        $this->assertSame(
            ['id', 'name', 'description', 'created_at', 'updated_at'],
            array_keys($response->json('0')),
        );
    }

    public function test_rating_dimensions_api_returns_empty_array(): void
    {
        $this->getJson('/api/rating-dimensions')->assertOk()->assertExactJson([]);
    }

    /**
     * Ist-Zustand F-02: Die Zutaten-Prop enthält genau `id` und `name`.
     * Das Frontend (IngredientSearchArea.vue) erwartet zusätzlich ein Array
     * `category` – siehe tests/KnownDefects/Backend/IngredientSearchContractTest.
     */
    public function test_ingredient_search_page_lists_ingredients_with_id_and_name_only_f02(): void
    {
        $tofu = Ingredient::factory()->create(['name' => 'Tofu', 'description' => 'x']);
        $apfel = Ingredient::factory()->create(['name' => 'Apfel']);
        $usage = RecipeIngredient::factory()->create(['ingredient_id' => $tofu->id]);
        $usage->recipe->categories()->attach(Category::factory()->create());

        // Öffentlich: kein Login nötig
        $this->get(route('IngredientSearch'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('IngredientSearch')
                ->has('ingredients', 2)
                ->where('ingredients', [
                    ['id' => $apfel->id, 'name' => 'Apfel'],
                    ['id' => $tofu->id, 'name' => 'Tofu'],
                ])
            );
    }

    public function test_ingredient_search_page_with_authenticated_user(): void
    {
        $this->actingAsUser();
        Ingredient::factory()->create();

        $this->get(route('IngredientSearch'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('IngredientSearch')
                ->has('ingredients', 1, fn (Assert $i) => $i->hasAll(['id', 'name'])->missing('category'))
            );
    }

    /**
     * Ist-Zustand F-03: Die Seite wird ohne seitenspezifische Props gerendert.
     */
    public function test_my_ingredients_page_renders_without_props_f03(): void
    {
        $this->actingAsUser();
        Ingredient::factory()->count(2)->create();
        Recipe::factory()->create();

        $this->get(route('myIngredients'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MyIngredients')
                ->missing('ingredients')
            );

        $props = array_keys($this->get(route('myIngredients'))->viewData('page')['props']);
        sort($props);
        // Nur geteilte Props aus HandleInertiaRequests
        $this->assertSame(['auth', 'errors', 'name', 'quote', 'sidebarOpen'], $props);
    }

    public function test_my_ingredients_requires_login(): void
    {
        $this->get(route('myIngredients'))->assertRedirect(route('login'));
    }
}
