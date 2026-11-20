<?php

namespace Tests\Feature\Import;

use App\Jobs\ImportTikTokRecipe;
use App\Models\RecipeImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regressionstest zu F-24 (vormals bekannter Defekt): Läuft kein Queue-Worker
 * (oder stirbt er), blieb ein Import dauerhaft `pending`/`downloading`/
 * `analyzing`, recipeImports.show meldete ihn unbegrenzt als laufend.
 *
 * Sollverhalten: Ein länger als ImportTikTokRecipe::STALE_AFTER_MINUTES
 * (15 Minuten, Job-Timeout 600 s) unveränderter, laufender Import wird als
 * `failed` mit generischer Meldung gemeldet und so gespeichert.
 */
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
        $import = RecipeImport::factory()->for($host)->running($status)->create();

        $this->travel(30)->minutes();

        $response = $this->getJson(route('recipeImports.show', $import))->assertOk();

        $this->assertNotContains(
            $response->json('status'),
            ['pending', 'downloading', 'analyzing'],
            "Import ist seit 30 Minuten '{$status}' und wird weiterhin als laufend gemeldet (F-24).",
        );

        $response->assertExactJson([
            'id' => $import->id,
            'status' => 'failed',
            'source' => null,
            'error' => ImportTikTokRecipe::MESSAGE_TIMEOUT,
            // Slice 4: Fortschrittsfelder (Status != pending => started)
            'started' => true,
            'created_at' => $import->fresh()->created_at->copy()->utc()->toIso8601ZuluString(),
            'updated_at' => $import->fresh()->updated_at->copy()->utc()->toIso8601ZuluString(),
        ]);

        // Der Zustand wird gespeichert, nicht nur gemeldet
        $this->assertSame('failed', $import->fresh()->status);
        $this->assertSame(ImportTikTokRecipe::MESSAGE_TIMEOUT, $import->fresh()->error);
    }

    #[DataProvider('runningStates')]
    public function test_running_import_within_threshold_is_still_reported_as_running(string $status): void
    {
        $host = $this->actingAsHost();
        $import = RecipeImport::factory()->for($host)->running($status)->create();

        // Länger als der Job-Timeout (600 s), aber unter der Schwelle
        $this->travel(ImportTikTokRecipe::STALE_AFTER_MINUTES - 1)->minutes();

        $this->getJson(route('recipeImports.show', $import))
            ->assertOk()
            ->assertJson(['status' => $status, 'error' => null]);

        $this->assertSame($status, $import->fresh()->status);
    }

    public function test_progress_resets_the_stale_clock(): void
    {
        $host = $this->actingAsHost();
        $import = RecipeImport::factory()->for($host)->running('downloading')->create();

        $this->travel(10)->minutes();
        $import->update(['status' => 'analyzing']);
        $this->travel(10)->minutes();

        $this->getJson(route('recipeImports.show', $import))
            ->assertOk()
            ->assertJson(['status' => 'analyzing']);
    }

    public function test_old_finished_imports_are_not_changed(): void
    {
        $host = $this->actingAsHost();
        $done = RecipeImport::factory()->for($host)->done()->create();
        $failed = RecipeImport::factory()->for($host)->failed('Die Analyse ist fehlgeschlagen.')->create();

        $this->travel(2)->days();

        $this->getJson(route('recipeImports.show', $done))->assertOk()->assertJson(['status' => 'done', 'error' => null]);
        $this->getJson(route('recipeImports.show', $failed))->assertOk()->assertJson(['error' => 'Die Analyse ist fehlgeschlagen.']);
    }
}
