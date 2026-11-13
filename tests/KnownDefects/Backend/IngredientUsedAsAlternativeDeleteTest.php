<?php

namespace Tests\KnownDefects\Backend;

use App\Models\IngredientAlternative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Bekannter Defekt F-18: IngredientController::destroy prüft nur
 * recipeIngredients(); eine Zutat, die ausschließlich als Alternative
 * (ingredient_alternatives.alternative_ingredient_id, ohne onDelete) genutzt
 * wird, führt beim Löschen zu einer FK-Verletzung (500).
 *
 * Sollverhalten: 422 mit Meldung, die Zutat und die Alternative bleiben bestehen.
 */
#[Group('known-defect')]
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
            ->assertJsonStructure(['message']);

        $this->assertModelExists($ingredient);
        $this->assertModelExists($alternative);
    }
}
