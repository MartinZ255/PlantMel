<?php

namespace Tests\Feature\Catalog;

use App\Models\IngredientAlternative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regressionstest zu F-18 (behoben in Slice 1b): IngredientController::destroy
 * prüfte nur recipeIngredients(); eine Zutat, die ausschließlich als Alternative
 * (ingredient_alternatives.alternative_ingredient_id, ohne onDelete) genutzt
 * wird, führte beim Löschen zu einer FK-Verletzung (500).
 *
 * Sollverhalten: 422 im gleichen JSON-Format wie „in Rezept verwendet“
 * (`{message}`), Zutat und Alternative bleiben bestehen.
 */
#[Group('F-18')]
class IngredientUsedAsAlternativeDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_ingredient_used_only_as_alternative_returns_422(): void
    {
        $this->actingAsHost();
        $alternative = IngredientAlternative::factory()->create();
        $ingredient = $alternative->alternativeIngredient;

        $this->assertFalse($ingredient->recipeIngredients()->exists());

        $this->deleteJson(route('ingredients.destroy', $ingredient))
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Die Zutat ist in mindestens einem Rezept als Alternative hinterlegt und kann nicht gelöscht werden.',
            ]);

        $this->assertModelExists($ingredient);
        $this->assertModelExists($alternative);
    }
}
