<?php

namespace Tests\KnownDefects\Backend;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\RecipeIngredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Bekannter Defekt F-02: IngredientController::index liefert je Zutat nur `id`
 * und `name`; IngredientSearchArea.vue ruft `ingredient.category.map/.join` auf
 * (TypeError bei der ersten Eingabe).
 *
 * Sollverhalten: Jede Zutat der Seite `IngredientSearch` enthält ein
 * Array-Feld `category` (Vertrag, den das Frontend voraussetzt).
 */
#[Group('known-defect')]
#[Group('F-02')]
class IngredientSearchContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingredient_search_items_contain_category_array(): void
    {
        $usage = RecipeIngredient::factory()->create();
        $usage->recipe->categories()->attach(Category::factory()->create());
        Ingredient::factory()->create();

        $this->get(route('IngredientSearch'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('IngredientSearch')
                ->has('ingredients', 2)
                ->has('ingredients.0', fn (Assert $i) => $i
                    ->has('id')
                    ->has('name')
                    ->whereType('category', 'array')
                    ->etc()
                )
                ->has('ingredients.1', fn (Assert $i) => $i
                    ->whereType('category', 'array')
                    ->etc()
                )
            );
    }
}
