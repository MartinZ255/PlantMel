<?php

namespace Tests\KnownDefects\Recipes;

use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Bekannter Defekt F-14: Schritte werden als nummerierter Text gespeichert
 * (RecipeController::buildInstructionsText, :451-475) und beim Lesen an jedem
 * Zeilenumbruch getrennt (:65-97, :553-579). Ein mehrzeiliger Schritt wird so
 * bei jedem Bearbeiten zu mehreren Schritten.
 *
 * Sollverhalten: Ein Schritt bleibt nach store -> edit/show genau EIN Schritt.
 * Ob der Umbruch erhalten oder (Minimal-Fix) zu einem Leerzeichen normalisiert wird,
 * lässt der Test offen; beide Textteile müssen im selben Schritt stehen.
 * Gegenpart (Ist-Verhalten): Tests\Feature\Recipes\RecipeCurrentDefectBehaviourTest (…_f14).
 */
#[Group('known-defect')]
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
