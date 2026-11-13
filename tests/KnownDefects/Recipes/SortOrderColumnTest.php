<?php

namespace Tests\KnownDefects\Recipes;

use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Bekannter Defekt F-12: orderBy('sort_order') auf categories und rating_dimensions
 * (routes/web.php:107,109; RecipeController.php:303,306,517,520), aber keine Migration
 * legt die Spalte an. SQLite wertet den Bezeichner als String-Literal (kein Fehler),
 * MySQL/Postgres antworten mit 500.
 *
 * Sollverhalten: Jede Spalte, nach der Anlegen- und Bearbeiten-Formular diese Tabellen
 * sortieren, existiert im Schema. Beide zulässigen Fixes machen den Test grün:
 * additive Migration `sort_order` ODER Sortierung nach einer vorhandenen Spalte (z. B. name).
 *
 * Warum kein Reihenfolge-Test: Unter SQLite kommen Kategorien heute schon zufällig
 * alphabetisch an (Covering-Index auf name), ein Reihenfolge-Test wäre nicht zuverlässig rot.
 * Gegenpart (Ist-Verhalten): Tests\Feature\Recipes\RecipeCurrentDefectBehaviourTest (…_f12).
 */
#[Group('known-defect')]
#[Group('F-12')]
class SortOrderColumnTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    public function test_create_and_edit_only_order_by_existing_columns(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();

        foreach ([route('recipes.create'), route('recipes.edit', $recipe)] as $url) {
            foreach (['categories', 'rating_dimensions'] as $table) {
                $columns = $this->orderByColumnsFor($table, fn () => $this->get($url)->assertOk());

                $this->assertNotEmpty($columns, "$url sortiert $table nicht mehr – Reihenfolge undefiniert");

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
