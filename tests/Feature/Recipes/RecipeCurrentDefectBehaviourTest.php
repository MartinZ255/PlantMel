<?php

namespace Tests\Feature\Recipes;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientAlternative;
use App\Models\RatingDimension;
use App\Models\Recipe;
use App\Models\RecipeImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Grüne Charakterisierungstests für Ist-Verhalten, das zu bekannten Befunden aus
 * docs/modernization/FINDINGS.md gehört. Sie halten den HEUTIGEN Zustand fest,
 * damit jede Verhaltensänderung (auch die gewollte Behebung) sichtbar wird.
 *
 * Wird ein Befund behoben, schlägt der zugehörige Test hier fehl: Dann diesen Test
 * löschen und den Gegenpart aus tests/KnownDefects/Recipes in die Feature-Suite verschieben.
 */
class RecipeCurrentDefectBehaviourTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    /**
     * F-13: update() validiert alternativeIngredientIds, speichert sie aber nicht.
     */
    public function test_update_currently_ignores_alternative_ids_f13(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();
        $tofu = $this->attachIngredient($recipe, 'Tofu', '200 g');
        $seitan = Ingredient::factory()->create(['name' => 'Seitan']);
        $tempeh = Ingredient::factory()->create(['name' => 'Tempeh']);
        $jackfruit = Ingredient::factory()->create(['name' => 'Jackfruit']);
        IngredientAlternative::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $tofu->id,
            'alternative_ingredient_id' => $seitan->id,
        ]);

        $this->post(route('recipes.update', $recipe), $this->recipePayload([
            'ingredients' => [
                ['amount' => '200 g', 'name' => 'Tofu', 'alternativeIngredientIds' => [$tempeh->id]],
                ['amount' => '1', 'name' => 'Neue Zutat', 'alternativeIngredientIds' => [$jackfruit->id]],
            ],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('recipes.show', $recipe));

        // Nur die alte Alternative existiert noch; die gesendeten wurden verworfen.
        $this->assertSame(
            [[$tofu->id, $seitan->id]],
            IngredientAlternative::where('recipe_id', $recipe->id)
                ->get()
                ->map(fn ($a) => [$a->ingredient_id, $a->alternative_ingredient_id])
                ->all()
        );
    }

    /**
     * F-13: Alternativen einer entfernten Zutat bleiben verwaist in der Datenbank.
     */
    public function test_update_currently_keeps_alternatives_of_removed_ingredient_f13(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();
        $tofu = $this->attachIngredient($recipe, 'Tofu', '200 g');
        $seitan = Ingredient::factory()->create(['name' => 'Seitan']);
        IngredientAlternative::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $tofu->id,
            'alternative_ingredient_id' => $seitan->id,
        ]);

        $this->post(route('recipes.update', $recipe), $this->recipePayload([
            'ingredients' => [['amount' => '1', 'name' => 'Zwiebel']],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(['Zwiebel'], $this->ingredientNamesInDbOrder($recipe));
        $this->assertDatabaseHas('ingredient_alternatives', [
            'recipe_id' => $recipe->id,
            'ingredient_id' => $tofu->id,
            'alternative_ingredient_id' => $seitan->id,
        ]);

        // Wird Tofu später wieder hinzugefügt, taucht die alte Alternative wieder auf.
        $this->post(route('recipes.update', $recipe), $this->recipePayload([
            'ingredients' => [['amount' => '1', 'name' => 'Tofu']],
        ]));
        $form = $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe'];
        $this->assertSame([$seitan->id], $form['ingredients'][0]['alternativeIngredientIds']);
    }

    /**
     * F-14: Ein Schritt mit Zeilenumbruch wird als mehrere Zeilen gespeichert und beim
     * Lesen (show/edit) an jedem Umbruch in mehrere Schritte zerlegt.
     */
    public function test_multiline_step_is_currently_split_on_edit_f14(): void
    {
        $this->actingAsHost();

        $this->post(route('recipes.store'), $this->recipePayload([
            'steps' => ["Teig kneten\nund ruhen lassen", 'Backen'],
        ]))->assertSessionHasNoErrors();

        $recipe = Recipe::sole();
        $this->assertSame("1. Teig kneten\nund ruhen lassen\n2. Backen", $recipe->instructions);

        $form = $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe'];
        $this->assertSame(['Teig kneten', 'und ruhen lassen', 'Backen'], $form['steps']);

        $show = $this->pageProps($this->get(route('recipes.show', $recipe)))['recipe'];
        $this->assertSame(['Teig kneten', 'und ruhen lassen', 'Backen'], $show['steps']);
    }

    /**
     * F-14 (Variante): Enthält ein mehrzeiliger Schritt eine Zeile "Notizen", kippt der
     * Parser in den Notizen-Modus – alle folgenden Schritte landen in den Notizen.
     * Ein einzeiliger Schritt "Notizen" ist dagegen sicher, weil er als "2. Notizen"
     * gespeichert wird und die Notizen-Erkennung vor dem Entfernen der Nummer greift.
     */
    public function test_multiline_step_with_notizen_line_currently_turns_following_steps_into_notes_f14(): void
    {
        $this->actingAsHost();

        $this->post(route('recipes.store'), $this->recipePayload([
            'steps' => ['Schneiden', 'Notizen', "Kochen\nNotizen:\nAbschmecken", 'Servieren'],
        ]))->assertSessionHasNoErrors();

        $recipe = Recipe::sole();
        $form = $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe'];
        $this->assertSame(['Schneiden', 'Notizen', 'Kochen'], $form['steps']);
        $this->assertSame("Abschmecken\nServieren", $form['notes']);
    }

    /**
     * Neu beobachtet (kein eigener Befund, verwandt mit F-14): Nummerierte Notizzeilen
     * verlieren beim Lesen ihre Nummer, weil der Parser sie wie Schritte behandelt.
     */
    public function test_numbered_note_lines_currently_lose_their_numbering(): void
    {
        $this->actingAsHost();

        $this->post(route('recipes.store'), $this->recipePayload([
            'notes' => "1. Tipp\n2. Tipp",
        ]))->assertSessionHasNoErrors();

        $recipe = Recipe::sole();
        $this->assertSame("1. Linsen waschen.\nNotizen:\n1. Tipp\n2. Tipp", $recipe->instructions);
        $form = $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe'];
        $this->assertSame("Tipp\nTipp", $form['notes']);
    }

    /**
     * F-15: source wird validiert, aber nicht gespeichert; show/edit liefern fest null.
     */
    public function test_source_is_currently_not_persisted_f15(): void
    {
        $this->actingAsHost();

        $this->post(route('recipes.store'), $this->recipePayload([
            'source' => 'https://example.test/rezept',
        ]))->assertSessionHasNoErrors();

        $recipe = Recipe::sole();
        $this->assertFalse(Schema::hasColumn('recipes', 'source'));
        $this->assertNull($this->pageProps($this->get(route('recipes.show', $recipe)))['recipe']['source']);
        $this->assertNull($this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe']['source']);
    }

    /**
     * F-19: Beim Löschen verschwindet nur die DB-Zeile (Kaskade), die Datei bleibt liegen.
     */
    public function test_delete_currently_leaves_image_file_on_disk_f19(): void
    {
        Storage::fake('public');
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();
        Storage::disk('public')->put('recipes/bild.png', 'inhalt');
        RecipeImage::factory()->create(['recipe_id' => $recipe->id, 'image_path' => 'recipes/bild.png']);

        $this->delete(route('recipes.delete', $recipe))->assertRedirect(route('recipes.index'));

        $this->assertDatabaseCount('recipe_images', 0);
        Storage::disk('public')->assertExists('recipes/bild.png');
    }

    /**
     * F-12: Es gibt keine Spalte sort_order, trotzdem wird danach sortiert. Unter SQLite
     * wird "sort_order" als String-Literal gewertet: kein Fehler (HTTP 200), aber auch keine
     * definierte Sortierung. Unter MySQL/Postgres wäre das ein 500 – die Suite läuft nur gegen SQLite.
     *
     * Beobachtung (bewusst NICHT als Assertion, da vom Query-Planer abhängig): Kategorien
     * kommen zufällig alphabetisch (SQLite liest über den UNIQUE-Index auf name als
     * Covering-Index), Bewertungsdimensionen in Einfügereihenfolge.
     */
    public function test_create_and_edit_currently_order_by_missing_sort_order_column_f12(): void
    {
        $this->assertFalse(Schema::hasColumn('categories', 'sort_order'));
        $this->assertFalse(Schema::hasColumn('rating_dimensions', 'sort_order'));

        $this->actingAsHost();
        Category::factory()->count(3)->create();
        RatingDimension::factory()->count(3)->create();
        $recipe = Recipe::factory()->create();

        foreach ([route('recipes.create'), route('recipes.edit', $recipe)] as $url) {
            $categoryOrder = $this->orderByColumnsFor('categories', function () use ($url, &$response) {
                $response = $this->get($url);
            });
            $response->assertOk();
            $this->assertSame(['sort_order'], $categoryOrder, $url);

            $dimensionOrder = $this->orderByColumnsFor('rating_dimensions', fn () => $this->get($url)->assertOk());
            $this->assertSame(['sort_order'], $dimensionOrder, $url);

            $this->assertCount(3, $this->pageProps($response)['categories']);
            $this->assertCount(3, $this->pageProps($response)['ratingDimensions']);
        }
    }

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
