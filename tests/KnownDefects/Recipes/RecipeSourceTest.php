<?php

namespace Tests\KnownDefects\Recipes;

use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Bekannter Defekt F-15: `source` wird validiert (RecipeController.php:326,595),
 * aber nirgends gespeichert; show/edit liefern fest null (:150,536).
 *
 * Sollverhalten: Die Quelle wird gespeichert und in show und edit zurückgegeben.
 * (Falls stattdessen das Feld entfernt wird, ist dieser Test zu löschen – per ADR.)
 * Gegenpart (Ist-Verhalten): Tests\Feature\Recipes\RecipeCurrentDefectBehaviourTest (…_f15).
 */
#[Group('known-defect')]
#[Group('F-15')]
class RecipeSourceTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    public function test_source_is_persisted_and_returned_in_show_and_edit(): void
    {
        $this->actingAsHost();

        $this->post(route('recipes.store'), $this->recipePayload([
            'source' => 'https://example.test/rezept',
        ]))->assertSessionHasNoErrors();

        $recipe = Recipe::sole();

        $this->assertSame(
            'https://example.test/rezept',
            $this->pageProps($this->get(route('recipes.show', $recipe)))['recipe']['source']
        );
        $this->assertSame(
            'https://example.test/rezept',
            $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe']['source']
        );
    }

    public function test_source_is_updated(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();

        $this->post(route('recipes.update', $recipe), $this->recipePayload([
            'source' => 'Omas Kochbuch, S. 12',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            'Omas Kochbuch, S. 12',
            $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe']['source']
        );
    }
}
