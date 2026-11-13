<?php

namespace Tests\KnownDefects\Backend;

use App\Models\Category;
use App\Models\IngredientAlternative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Bekannter Defekt F-22 (Validierungslücken in Stammdaten-Endpunkten):
 * - IngredientAlternativeController::store prüft die Eindeutigkeit
 *   (recipe_id, ingredient_id, alternative_ingredient_id) nicht; der
 *   Unique-Index wirft eine QueryException (500).
 * - CategoryController::store/update validieren `max:255`, die Spalte
 *   categories.name hat aber nur 100 Zeichen. Unter SQLite wird die Länge nicht
 *   erzwungen (Name wird gespeichert), unter MySQL strict wäre es ein 500.
 *
 * Sollverhalten: 422 mit Validierungsfehler, keine Datenänderung.
 */
#[Group('known-defect')]
#[Group('F-22')]
class CatalogValidationGapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_alternative_is_rejected_with_422(): void
    {
        $this->actingAsHost();
        $existing = IngredientAlternative::factory()->create();

        $this->postJson(
            route('ingredientAlternatives.store', [$existing->recipe_id, $existing->ingredient_id]),
            ['alternative_ingredient_id' => $existing->alternative_ingredient_id],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('alternative_ingredient_id');

        $this->assertDatabaseCount('ingredient_alternatives', 1);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function tooLongCategoryNames(): array
    {
        return [
            '101 Zeichen' => [101],
            '255 Zeichen' => [255],
        ];
    }

    #[DataProvider('tooLongCategoryNames')]
    public function test_category_store_rejects_names_longer_than_column(int $length): void
    {
        $this->actingAsHost();

        $this->postJson(route('categories.store'), ['name' => str_repeat('a', $length)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_category_update_rejects_name_longer_than_column(): void
    {
        $this->actingAsHost();
        $category = Category::factory()->create(['name' => 'Kurz']);

        $this->putJson(route('categories.update', $category), ['name' => str_repeat('b', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame('Kurz', $category->fresh()->name);
    }
}
