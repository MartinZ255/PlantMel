<?php

namespace Tests\KnownDefects\Backend;

use App\Models\Ingredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Bekannter Defekt F-03: Die Closure-Route `myIngredients` (routes/web.php)
 * rendert `MyIngredients` ohne Props; die Seite reicht `ingredients` als
 * Pflicht-Prop an IngredientSearchArea.vue weiter (TypeError beim Rendern).
 *
 * Sollverhalten: Die Route liefert die Prop `ingredients`.
 *
 * Hinweis: Ob F-03 durch Umsetzen oder Entfernen der Seite gelöst wird, ist eine
 * offene Produktentscheidung (ROADMAP). Wird die Seite entfernt, ist dieser
 * Test zu löschen statt zu verschieben.
 */
#[Group('known-defect')]
#[Group('F-03')]
class MyIngredientsPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_ingredients_page_provides_ingredients_prop(): void
    {
        $this->actingAsUser();
        Ingredient::factory()->count(2)->create();

        $this->get(route('myIngredients'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MyIngredients')
                ->has('ingredients')
            );
    }
}
