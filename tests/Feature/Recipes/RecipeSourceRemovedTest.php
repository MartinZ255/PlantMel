<?php

namespace Tests\Feature\Recipes;

use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * F-15 (Entscheidung des Auftraggebers, DECISIONS.md): Das Feld „Quelle“
 * entfällt. Es wurde nie gespeichert (keine Spalte recipes.source); show und
 * edit lieferten fest null. Ersetzt den früheren roten Test RecipeSourceTest.
 *
 * Sollverhalten: `source` wird weder gespeichert noch ausgeliefert; sendet das
 * Frontend das Feld noch mit, wird es ignoriert (kein Validierungsfehler).
 */
#[Group('F-15')]
class RecipeSourceRemovedTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    public function test_sent_source_is_ignored_on_store_and_not_returned(): void
    {
        $this->actingAsHost();

        $this->post(route('recipes.store'), $this->recipePayload([
            'source' => 'https://example.test/rezept',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $recipe = Recipe::sole();
        $this->assertFalse(Schema::hasColumn('recipes', 'source'));
        $this->assertArrayNotHasKey('source', $recipe->getAttributes());

        $this->assertArrayNotHasKey('source', $this->pageProps($this->get(route('recipes.show', $recipe)))['recipe']);
        $this->assertArrayNotHasKey('source', $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe']);
    }

    public function test_sent_source_is_ignored_on_update(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();

        $this->post(route('recipes.update', $recipe), $this->recipePayload([
            'name' => 'Neu',
            'source' => 'Omas Kochbuch, S. 12',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('recipes.show', $recipe));

        $this->assertSame('Neu', $recipe->fresh()->title);
        $this->assertArrayNotHasKey('source', $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe']);
    }

    public function test_source_of_any_type_no_longer_causes_validation_errors(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();

        // Früher: 'nullable|string' -> ein Array war ein 422-Fehler
        $this->post(route('recipes.store'), $this->recipePayload(['source' => ['x' => 'y']]))
            ->assertSessionHasNoErrors();
        $this->post(route('recipes.update', $recipe), $this->recipePayload(['source' => str_repeat('q', 70000)]))
            ->assertSessionHasNoErrors();
    }
}
