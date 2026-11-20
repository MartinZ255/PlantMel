<?php

namespace Tests\Feature\Recipes;

use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Regressionstest zu F-14 (behoben in Slice 2, ADR-001): Ein mehrzeiliger
 * Schritt wurde beim Lesen an jedem Zeilenumbruch getrennt und bei jedem
 * Bearbeiten zu mehreren Schritten.
 *
 * Sollverhalten: Ein Schritt bleibt nach store -> edit/show genau EIN Schritt.
 * Ausführliche Roundtrip-Fälle: RecipeInstructionsRoundTripTest.
 */
#[Group('F-14')]
class RecipeMultilineStepTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    public function test_multiline_step_stays_one_step_after_store_and_edit(): void
    {
        $this->actingAsHost();

        $this->post(route('recipes.store'), $this->recipePayload([
            'steps' => ["Teig kneten\nund ruhen lassen", 'Backen'],
        ]))->assertSessionHasNoErrors();

        $recipe = Recipe::sole();

        foreach ([route('recipes.edit', $recipe), route('recipes.show', $recipe)] as $url) {
            $steps = $this->pageProps($this->get($url))['recipe']['steps'];

            $this->assertCount(2, $steps, $url);
            $this->assertStringContainsString('Teig kneten', $steps[0], $url);
            $this->assertStringContainsString('und ruhen lassen', $steps[0], $url);
            $this->assertSame('Backen', $steps[1], $url);
        }
    }
}
