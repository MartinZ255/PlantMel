<?php

namespace Tests\Feature\Catalog;

use App\Models\Ingredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regressionstest zu F-03 (Backend-Teil, behoben in Slice 1b): Die
 * Closure-Route `myIngredients` (routes/web.php) renderte `MyIngredients` ohne
 * Props; die Seite reicht `ingredients` als Pflicht-Prop an
 * IngredientSearchArea.vue weiter (TypeError beim Rendern).
 *
 * Entscheidung des Nutzers: „Meine Zutaten“ bleibt als Feature bestehen und
 * wird später ausgebaut. Bis dahin liefert die Route `ingredients` als leeres
 * Array (keine gespeicherten eigenen Zutaten, auch wenn der Katalog Zutaten hat).
 */
#[Group('F-03')]
class MyIngredientsPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_ingredients_page_provides_empty_ingredients_prop(): void
    {
        $this->actingAsUser();
        Ingredient::factory()->count(2)->create();

        $this->get(route('myIngredients'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MyIngredients')
                ->where('ingredients', [])
            );
    }
}
