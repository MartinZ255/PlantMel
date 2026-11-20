<?php

namespace Tests\Feature\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Entscheidung E15 (2026-11-20): Die separaten Endpunkte für Zutaten-Alternativen
 * wurden entfernt, weil das Frontend sie nie aufgerufen hat. Alternativen werden
 * mit dem Rezept gespeichert (RecipeAlternativesSyncTest,
 * RecipeUpdateAlternativesTest) und auf der Rezeptseite angezeigt
 * (RecipeShowTest).
 */
class IngredientAlternativeRoutesRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_alternative_route_names_no_longer_exist(): void
    {
        foreach (['ingredientAlternatives.index', 'ingredientAlternatives.store', 'ingredientAlternatives.destroy'] as $name) {
            $this->assertFalse(Route::has($name), "Route {$name} sollte entfernt sein.");
        }
    }

    public function test_old_alternative_urls_answer_not_found_for_host(): void
    {
        $this->actingAsHost();

        $this->getJson('/recipes/1/ingredients/1/alternatives')->assertNotFound();
        $this->postJson('/recipes/1/ingredients/1/alternatives', ['alternative_ingredient_id' => 1])->assertNotFound();
        $this->deleteJson('/ingredient-alternatives/1')->assertNotFound();
    }
}
