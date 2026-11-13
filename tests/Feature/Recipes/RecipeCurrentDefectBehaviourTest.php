<?php

namespace Tests\Feature\Recipes;

use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Grüne Charakterisierungstests für Ist-Verhalten, das zu bekannten Befunden aus
 * docs/modernization/FINDINGS.md gehört. Sie halten den HEUTIGEN Zustand fest,
 * damit jede Verhaltensänderung (auch die gewollte Behebung) sichtbar wird.
 *
 * Wird ein Befund behoben, schlägt der zugehörige Test hier fehl: Dann diesen Test
 * löschen und den Gegenpart aus tests/KnownDefects/Recipes in die Feature-Suite verschieben.
 *
 * Behoben in Slice 2 und deshalb entfernt: F-12 (SortOrderColumnTest), F-13
 * (RecipeUpdateAlternativesTest, RecipeAlternativesSyncTest), F-14/F-66
 * (RecipeMultilineStepTest, RecipeInstructionsRoundTripTest), F-15
 * (RecipeSourceRemovedTest), F-19 (RecipeDeleteImageTest, RecipeImageFilesTest).
 */
class RecipeCurrentDefectBehaviourTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    // F-22 (behoben in Slice 1b): Die früheren Ist-Tests zu `?search[]=a` und
    // unbekannten Bewertungsdimensionen stehen jetzt als Regressionstests in
    // Tests\Feature\Recipes\RecipeInputValidationTest.

    /**
     * F-23: Die Liste liefert alle Rezepte ohne Pagination.
     */
    public function test_index_currently_returns_all_recipes_without_pagination_f23(): void
    {
        Recipe::factory()->count(60)->create();

        $props = $this->pageProps($this->get(route('recipes.index')));

        $this->assertCount(60, $props['recipes']);
        $this->assertArrayNotHasKey('links', $props);
        $this->assertArrayNotHasKey('meta', $props);
    }
}
