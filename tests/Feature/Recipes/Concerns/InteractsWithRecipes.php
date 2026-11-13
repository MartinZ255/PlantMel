<?php

namespace Tests\Feature\Recipes\Concerns;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Gemeinsame Helfer für die Rezept-Tests (Feature und KnownDefects).
 *
 * Die Payload-Struktur entspricht dem, was resources/js/components/recipes/RecipeForm.vue
 * per useForm(...).post(..., { forceFormData: true }) sendet:
 * name, description, duration_minutes, servings, image, tags[],
 * ingredients[][amount|name|alternativeIngredientIds[]], steps[], notes, ratings{dimensionId: wert}.
 * Das frühere Feld `source` entfällt (F-15); ein mitgesendetes wird ignoriert
 * (RecipeSourceRemovedTest).
 */
trait InteractsWithRecipes
{
    /**
     * Minimal gültiger Payload für recipes.store / recipes.update.
     *
     * @param  array<string, mixed>  $overrides  ersetzt Schlüssel der obersten Ebene
     * @return array<string, mixed>
     */
    protected function recipePayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Linsen-Bolognese',
            'description' => 'Herzhafte Sauce mit roten Linsen.',
            'duration_minutes' => 45,
            'servings' => 4,
            'tags' => [],
            'ingredients' => [
                ['amount' => '200 g', 'name' => 'Rote Linsen'],
            ],
            'steps' => ['Linsen waschen.'],
            'notes' => '',
            'ratings' => [],
        ], $overrides);
    }

    /**
     * Echtes 1x1-PNG ohne GD-Abhängigkeit (UploadedFile::fake()->image() braucht
     * je nach Format imagejpeg/imagepng; lokal fehlt z. B. JPEG-Support in GD).
     */
    protected function fakePng(string $name = 'bild.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')
        );
    }

    /**
     * Inertia-Props der Antwort als Array (Arrayables sind bereits aufgelöst).
     *
     * @return array<string, mixed>
     */
    protected function pageProps(TestResponse $response): array
    {
        $page = $response->viewData('page');

        return $page['props'];
    }

    /**
     * Hängt eine Zutat (Name) mit Mengenangabe in `note` an ein Rezept an –
     * so, wie der Controller es speichert (quantity/unit = null).
     */
    protected function attachIngredient(Recipe $recipe, string $name, ?string $amount = null): Ingredient
    {
        $ingredient = Ingredient::firstOrCreate(['name' => $name]);

        RecipeIngredient::factory()->withoutQuantity()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'note' => $amount,
        ]);

        return $ingredient;
    }

    /**
     * Führt $callback aus und sammelt für jede SELECT-Abfrage auf $table die
     * Spalten aus der ORDER-BY-Klausel (Laravel quotet sie unter SQLite als "spalte").
     *
     * @return list<string>
     */
    protected function orderByColumnsFor(string $table, callable $callback): array
    {
        $columns = [];

        DB::listen(function (QueryExecuted $query) use ($table, &$columns) {
            if (! preg_match('/from "'.preg_quote($table, '/').'"/i', $query->sql)) {
                return;
            }

            if (preg_match('/order by (.+)$/is', $query->sql, $m)) {
                // optional tabellenqualifiziert: "tabelle"."spalte" -> spalte
                preg_match_all('/(?:"[a-z0-9_]+"\.)?"([a-z0-9_]+)"/i', $m[1], $cols);
                foreach ($cols[1] as $col) {
                    $columns[] = $col;
                }
            }
        });

        $callback();

        return array_values(array_unique($columns));
    }

    /**
     * Namen der Zutaten eines Rezepts in DB-Reihenfolge (id aufsteigend).
     *
     * @return list<string>
     */
    protected function ingredientNamesInDbOrder(Recipe $recipe): array
    {
        return RecipeIngredient::query()
            ->where('recipe_id', $recipe->id)
            ->orderBy('id')
            ->with('ingredient')
            ->get()
            ->map(fn (RecipeIngredient $ri) => $ri->ingredient->name)
            ->all();
    }
}
