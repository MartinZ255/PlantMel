<?php

namespace Tests\Feature\Import;

use App\Http\Controllers\RecipeImportController;
use App\Jobs\ImportTikTokRecipe;
use App\Models\RecipeImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regressionstest zu F-26 (vormals bekannter Defekt): recipeImports.store
 * hatte kein `throttle` und kein Limit paralleler Importe.
 *
 * Sollverhalten: Der 6. Import-Start innerhalb einer Stunde wird mit 429
 * gedrosselt; ein zweiter Import, solange ein eigener noch läuft, wird mit
 * 409 abgelehnt. Ein veralteter (hängengebliebener) Import blockiert nicht.
 */
#[Group('F-26')]
class ImportThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        // Der Rate-Limiter zählt im Cache ("array", pro Test neu). Zusätzlich
        // explizit leeren, damit die Tests unabhängig von der Reihenfolge sind.
        $this->assertSame('array', config('cache.default'));
        Cache::flush();
    }

    public function test_sixth_import_within_an_hour_is_throttled(): void
    {
        $this->actingAsHost();

        foreach (range(1, 5) as $i) {
            $this->postJson(route('recipeImports.store'), [
                'url' => "https://www.tiktok.com/@example/video/700000000000000000{$i}",
            ])->assertOk();

            // Vorherigen Import abschließen, damit nur das Rate-Limit greift
            RecipeImport::query()->update(['status' => 'done']);
        }

        $this->postJson(route('recipeImports.store'), [
            'url' => 'https://www.tiktok.com/@example/video/7000000000000000006',
        ])
            ->assertStatus(429)
            ->assertJson(['message' => 'Zu viele Importe in kurzer Zeit. Bitte später erneut versuchen.']);

        $this->assertSame(5, RecipeImport::count());
        $this->assertSame(5, Queue::pushed(ImportTikTokRecipe::class)->count());
    }

    public function test_limit_is_per_user_and_resets_after_an_hour(): void
    {
        $first = $this->actingAsHost();

        foreach (range(1, 6) as $i) {
            $this->postJson(route('recipeImports.store'), ['url' => "https://www.tiktok.com/@example/video/{$i}"]);
            RecipeImport::query()->update(['status' => 'done']);
        }

        $this->postJson(route('recipeImports.store'), ['url' => 'https://www.tiktok.com/@example/video/7'])
            ->assertStatus(429);

        // Anderer Nutzer ist nicht betroffen
        $this->actingAsHost();
        $this->postJson(route('recipeImports.store'), ['url' => 'https://www.tiktok.com/@example/video/8'])
            ->assertOk();

        // Nach einer Stunde darf der erste Nutzer wieder
        $this->travel(61)->minutes();
        $this->actingAs($first);
        $this->postJson(route('recipeImports.store'), ['url' => 'https://www.tiktok.com/@example/video/9'])
            ->assertOk();
    }

    public function test_second_import_while_own_import_is_running_is_rejected(): void
    {
        $host = $this->actingAsHost();
        RecipeImport::factory()->for($host)->running('analyzing')->create();

        $this->postJson(route('recipeImports.store'), [
            'url' => 'https://www.tiktok.com/@example/video/7000000000000000009',
        ])
            ->assertStatus(409)
            ->assertExactJson(['message' => RecipeImportController::MESSAGE_ALREADY_RUNNING]);

        $this->assertSame(1, RecipeImport::count());
        Queue::assertNothingPushed();
    }

    public function test_second_start_right_after_first_is_rejected(): void
    {
        $this->actingAsHost();

        $this->postJson(route('recipeImports.store'), ['url' => 'https://www.tiktok.com/@example/video/1'])
            ->assertOk();
        $this->postJson(route('recipeImports.store'), ['url' => 'https://www.tiktok.com/@example/video/2'])
            ->assertStatus(409);

        $this->assertSame(1, RecipeImport::count());
        Queue::assertPushed(ImportTikTokRecipe::class, 1);
    }

    public function test_running_import_of_another_user_does_not_block(): void
    {
        RecipeImport::factory()->for(User::factory()->host())->running()->create();
        $this->actingAsHost();

        $this->postJson(route('recipeImports.store'), ['url' => 'https://www.tiktok.com/@example/video/1'])
            ->assertOk();
    }

    public function test_finished_imports_do_not_block(): void
    {
        $host = $this->actingAsHost();
        RecipeImport::factory()->for($host)->done()->create();
        RecipeImport::factory()->for($host)->failed()->create();

        $this->postJson(route('recipeImports.store'), ['url' => 'https://www.tiktok.com/@example/video/1'])
            ->assertOk();
    }

    /**
     * F-24: Ein hängengebliebener Import blockiert den Nutzer nicht dauerhaft
     * und wird beim nächsten Start als fehlgeschlagen abgeschlossen.
     */
    public function test_stale_running_import_does_not_block_and_is_marked_failed(): void
    {
        $host = $this->actingAsHost();
        $stale = RecipeImport::factory()->for($host)->running('downloading')->create();

        $this->travel(ImportTikTokRecipe::STALE_AFTER_MINUTES + 1)->minutes();

        $this->postJson(route('recipeImports.store'), ['url' => 'https://www.tiktok.com/@example/video/1'])
            ->assertOk();

        $this->assertSame('failed', $stale->fresh()->status);
        $this->assertSame(ImportTikTokRecipe::MESSAGE_TIMEOUT, $stale->fresh()->error);
        $this->assertSame(2, RecipeImport::count());
    }

    public function test_running_import_just_below_stale_threshold_still_blocks(): void
    {
        $host = $this->actingAsHost();
        RecipeImport::factory()->for($host)->running('pending')->create();

        $this->travel(ImportTikTokRecipe::STALE_AFTER_MINUTES - 1)->minutes();

        $this->postJson(route('recipeImports.store'), ['url' => 'https://www.tiktok.com/@example/video/1'])
            ->assertStatus(409);
    }
}
