<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Zutatensuche-Seite und "Meine Zutaten"-Seite.
 *
 * Die frühere API `/api/rating-dimensions` wurde in Slice 1b entfernt (F-16,
 * E6; vom Frontend nicht genutzt). Bewertungsdimensionen erreichen das
 * Frontend über die Props von recipes.create/recipes.edit.
 */
class CatalogPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Vertrag F-02 (Entscheidung Slice 1): Die Zutaten-Prop enthält bewusst
     * genau `id` und `name`, kein `category`. Das Frontend
     * (IngredientSearchArea.vue) wird so angepasst, dass es ohne `category`
     * auskommt. Eine Erweiterung des Vertrags braucht eine eigene Entscheidung.
     */
    public function test_ingredient_search_contract_is_id_and_name_only_f02(): void
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
     * F-03 (Slice 1b): Neben den geteilten Props liefert die Seite nur
     * `ingredients` (leer, siehe MyIngredientsPropsTest).
     */
    public function test_my_ingredients_page_props_f03(): void
    {
        $this->actingAsUser();
        Ingredient::factory()->count(2)->create();
        Recipe::factory()->create();

        $props = array_keys($this->get(route('myIngredients'))->assertOk()->viewData('page')['props']);
        sort($props);
        // Geteilte Props aus HandleInertiaRequests + `ingredients`
        // Slice 3: `flash` neu (F-17), `quote` und `sidebarOpen` entfallen.
        // E17: `features` (googleLogin) neu.
        $this->assertSame(['auth', 'errors', 'features', 'flash', 'ingredients', 'name'], $props);
    }

    public function test_my_ingredients_requires_login(): void
    {
        $this->get(route('myIngredients'))->assertRedirect(route('login'));
    }
}
