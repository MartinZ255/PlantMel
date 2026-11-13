<?php

namespace Tests\KnownDefects\Backend;

use App\Models\RecipeImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Bekannter Defekt F-24: Läuft kein Queue-Worker (oder stirbt er), bleibt ein
 * Import dauerhaft `pending`/`downloading`/`analyzing`. recipeImports.show
 * meldet ihn unbegrenzt als laufend, der Client pollt ohne Obergrenze.
 *
 * Sollverhalten: Ein seit langer Zeit (hier 30 Minuten, Job-Timeout 600 s)
 * unveränderter, laufender Import wird nicht mehr als laufend gemeldet.
 */
#[Group('known-defect')]
#[Group('F-24')]
class StaleImportStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function runningStates(): array
    {
        return [
            'pending' => ['pending'],
            'downloading' => ['downloading'],
            'analyzing' => ['analyzing'],
        ];
    }

    #[DataProvider('runningStates')]
    public function test_stale_running_import_is_no_longer_reported_as_running(string $status): void
    {
        $host = $this->actingAsHost();
        $import = RecipeImport::factory()->for($host)->create(['status' => $status]);

        $this->travel(30)->minutes();

        $response = $this->getJson(route('recipeImports.show', $import))->assertOk();

        $this->assertNotContains(
            $response->json('status'),
            ['pending', 'downloading', 'analyzing'],
            "Import ist seit 30 Minuten '{$status}' und wird weiterhin als laufend gemeldet (F-24).",
        );
    }
}
