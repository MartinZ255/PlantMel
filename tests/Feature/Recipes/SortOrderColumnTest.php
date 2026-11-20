<?php

namespace Tests\Feature\Recipes;

use App\Models\Category;
use App\Models\RatingDimension;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Regressionstest zu F-12 (behoben in Slice 2, Entscheidung E5): orderBy('sort_order')
 * auf categories und rating_dimensions, obwohl keine Migration die Spalte anlegt.
 * SQLite wertete den Bezeichner als String-Literal (kein Fehler), MySQL/Postgres
 * hätten mit 500 geantwortet.
 *
 * Sollverhalten: Anlegen- und Bearbeiten-Formular sortieren Kategorien nach `name`.
 * Seit E14 liefern sie nur noch die Geschmacks-Dimension (Lookup über den
 * eindeutigen Namen, höchstens eine Zeile); dort wird gar nicht mehr sortiert,
 * es darf aber weiterhin keine fehlende Spalte wie `sort_order` vorkommen.
 */
#[Group('F-12')]
class SortOrderColumnTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    public function test_create_and_edit_order_categories_by_name_and_return_only_taste_dimension(): void
    {
        $this->actingAsHost();
        // Einfügereihenfolge absichtlich nicht alphabetisch
        foreach (['Würzig', 'Abendessen', 'Mittag'] as $name) {
            Category::factory()->create(['name' => $name]);
        }
        foreach (['Optik', 'Aufwand', 'Geschmack'] as $name) {
            RatingDimension::factory()->create(['name' => $name]);
        }
        $recipe = Recipe::factory()->create();

        foreach ([route('recipes.create'), route('recipes.edit', $recipe)] as $url) {
            $columns = $this->orderByColumnsFor('categories', fn () => $this->get($url)->assertOk());
            $this->assertSame(['name'], $columns, "$url sortiert categories");

            $props = $this->pageProps($this->get($url));
            $this->assertSame(['Abendessen', 'Mittag', 'Würzig'], array_column($props['categories'], 'name'), $url);
            // E14: nur Geschmack (vorher alle Dimensionen alphabetisch)
            $this->assertSame(['Geschmack'], array_column($props['ratingDimensions'], 'name'), $url);
        }
    }

    public function test_create_and_edit_only_order_by_existing_columns(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();

        foreach ([route('recipes.create'), route('recipes.edit', $recipe)] as $url) {
            foreach (['categories', 'rating_dimensions'] as $table) {
                $columns = $this->orderByColumnsFor($table, fn () => $this->get($url)->assertOk());

                // rating_dimensions: seit E14 Lookup einer Zeile per Name, ohne Sortierung
                if ($table === 'categories') {
                    $this->assertNotEmpty($columns, "$url sortiert $table nicht mehr – Reihenfolge undefiniert");
                }

                foreach ($columns as $column) {
                    $this->assertTrue(
                        Schema::hasColumn($table, $column),
                        "$url sortiert $table nach fehlender Spalte \"$column\""
                    );
                }
            }
        }
    }
}
