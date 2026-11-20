<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regressionstest zu F-22 (Validierungslücken in Stammdaten-Endpunkten,
 * behoben in Slice 1b):
 * - CategoryController::store/update validierten `max:255`, die Spalte
 *   categories.name hat aber nur 100 Zeichen. Jetzt `max:100`.
 *
 * Sollverhalten: 422 mit Validierungsfehler, keine Datenänderung.
 */
#[Group('F-22')]
class CatalogValidationGapsTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_category_store_accepts_name_at_column_limit(): void
    {
        $this->actingAsHost();

        $this->postJson(route('categories.store'), ['name' => str_repeat('a', 100)])
            ->assertCreated();
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
