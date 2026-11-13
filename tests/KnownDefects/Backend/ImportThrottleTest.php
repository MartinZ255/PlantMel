<?php

namespace Tests\KnownDefects\Backend;

use App\Jobs\ImportTikTokRecipe;
use App\Models\RecipeImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Bekannter Defekt F-26: recipeImports.store hat kein `throttle` und kein Limit
 * paralleler Importe (routes/web.php, RecipeImportController::store). Ein
 * Host-Account kann beliebig viele yt-dlp-/Gemini-Läufe anstoßen.
 *
 * Sollverhalten: Der 6. Import-Start innerhalb kurzer Zeit wird mit 429
 * gedrosselt; ein zweiter Import, solange ein eigener noch läuft, wird abgelehnt.
 */
#[Group('known-defect')]
#[Group('F-26')]
class ImportThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_sixth_import_within_a_minute_is_throttled(): void
    {
        $this->actingAsHost();

        foreach (range(1, 5) as $i) {
            $this->postJson(route('recipeImports.store'), [
                'url' => "https://www.tiktok.com/@example/video/700000000000000000{$i}",
            ]);
        }

        $this->postJson(route('recipeImports.store'), [
            'url' => 'https://www.tiktok.com/@example/video/7000000000000000006',
        ])->assertStatus(429);

        $this->assertLessThanOrEqual(5, RecipeImport::count());
        $this->assertLessThanOrEqual(5, Queue::pushed(ImportTikTokRecipe::class)->count());
    }

    public function test_second_import_while_own_import_is_running_is_rejected(): void
    {
        $host = $this->actingAsHost();
        RecipeImport::factory()->for($host)->create(['status' => 'analyzing']);

        $response = $this->postJson(route('recipeImports.store'), [
            'url' => 'https://www.tiktok.com/@example/video/7000000000000000009',
        ]);

        $this->assertContains(
            $response->status(),
            [409, 422, 429],
            "Zweiter paralleler Import wurde mit Status {$response->status()} angenommen (F-26).",
        );
        $this->assertSame(1, RecipeImport::count());
        Queue::assertNothingPushed();
    }
}
