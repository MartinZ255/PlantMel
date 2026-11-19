<?php

namespace Tests\Feature\Import;

use App\Jobs\ImportTikTokRecipe;
use App\Models\RecipeImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Import\Concerns\FakesTikTokImport;
use Tests\TestCase;

/**
 * Slice 4 (F-24, Fortschrittsanzeige): recipeImports.show liefert neben
 * id/status/source/error auch `started`, `created_at` und `updated_at`.
 * Ausserdem wird die tatsaechliche Statusfolge des Jobs festgehalten, aus der
 * das Frontend seine Schritte baut.
 */
class ImportProgressStatusTest extends TestCase
{
    use FakesTikTokImport;
    use RefreshDatabase;

    private const ISO_UTC = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureImporter();
    }

    protected function tearDown(): void
    {
        $this->cleanupImportTempFiles();

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function statuses(): array
    {
        return [
            'pending' => ['pending', false],
            'downloading' => ['downloading', true],
            'analyzing' => ['analyzing', true],
            'done' => ['done', true],
            'failed' => ['failed', true],
        ];
    }

    #[DataProvider('statuses')]
    public function test_show_reports_started_flag_per_status(string $status, bool $started): void
    {
        $host = $this->actingAsHost();
        $import = RecipeImport::factory()->for($host)->create(['status' => $status]);

        $this->getJson(route('recipeImports.show', $import))
            ->assertOk()
            ->assertJsonPath('status', $status)
            ->assertJsonPath('started', $started);
    }

    public function test_show_returns_iso_8601_utc_timestamps_and_keeps_existing_fields(): void
    {
        $host = $this->actingAsHost();

        $this->travelTo(now()->setDate(2026, 11, 14)->setTime(9, 30, 0));
        $import = RecipeImport::factory()->for($host)->pending()->create();

        $this->travelTo(now()->addSeconds(42));
        $import->update(['status' => 'downloading']);

        $response = $this->getJson(route('recipeImports.show', $import))->assertOk();

        $this->assertSame(
            ['id', 'status', 'source', 'error', 'started', 'created_at', 'updated_at'],
            array_keys($response->json()),
        );
        $response->assertJsonPath('id', $import->id)
            ->assertJsonPath('status', 'downloading')
            ->assertJsonPath('source', null)
            ->assertJsonPath('error', null)
            ->assertJsonPath('started', true)
            ->assertJsonPath('created_at', '2026-11-14T09:30:00Z')
            ->assertJsonPath('updated_at', '2026-11-14T09:30:42Z');

        $this->assertMatchesRegularExpression(self::ISO_UTC, $response->json('created_at'));
        $this->assertMatchesRegularExpression(self::ISO_UTC, $response->json('updated_at'));
    }

    public function test_caption_path_runs_pending_downloading_analyzing_done(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        $sequence = $this->recordStatusSequence($import);

        $this->fakeYtDlp(['title' => 'Bowl', 'description' => self::LONG_CAPTION]);
        $this->fakeGeminiVideoFlow(
            captionRecipe: [
                'is_complete' => true,
                'name' => 'Caption-Bowl',
                'ingredients' => [['amount' => '1', 'name' => 'Reis']],
                'steps' => ['Kochen.'],
                'tags' => [],
            ],
            videoRecipe: null,
        );

        (new ImportTikTokRecipe($import))->handle();

        $this->assertSame(['downloading', 'analyzing', 'done'], $sequence->statuses);
        $this->assertSame('caption', $import->fresh()->source);
    }

    /**
     * Video-Pfad: nach der Caption-Analyse springt der Status zurueck auf
     * `downloading` (Video-Download) und dann erneut auf `analyzing`
     * (Upload, Warten auf Gemini, Video-Analyse).
     */
    public function test_video_path_runs_downloading_analyzing_twice_then_done(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        $sequence = $this->recordStatusSequence($import);

        $this->fakeYtDlp(['title' => 'Bowl', 'description' => self::LONG_CAPTION]);
        $this->fakeGeminiVideoFlow(
            captionRecipe: ['is_complete' => false, 'name' => 'Bowl'],
            videoRecipe: [
                'is_complete' => true,
                'name' => 'Video-Bowl',
                'ingredients' => [['amount' => '1', 'name' => 'Avocado']],
                'steps' => ['Anrichten.'],
                'tags' => [],
            ],
        );

        (new ImportTikTokRecipe($import))->handle();

        $this->assertSame(['downloading', 'analyzing', 'downloading', 'analyzing', 'done'], $sequence->statuses);
        $this->assertSame('video', $import->fresh()->source);
    }

    public function test_failure_during_download_ends_in_failed(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        $sequence = $this->recordStatusSequence($import);

        Process::fake(['*' => Process::result(errorOutput: 'ERROR', exitCode: 1)]);
        Http::fake();

        try {
            (new ImportTikTokRecipe($import))->handle();
            $this->fail('Exception erwartet');
        } catch (\RuntimeException) {
            // erwartet: der Job wirft weiter, damit die Queue ihn als failed fuehrt
        }

        $this->assertSame(['downloading', 'failed'], $sequence->statuses);
    }

    /**
     * Zeichnet jeden gespeicherten Statuswechsel des Imports auf.
     */
    private function recordStatusSequence(RecipeImport $import): object
    {
        $sequence = new class
        {
            /** @var list<string> */
            public array $statuses = [];
        };

        RecipeImport::updated(function (RecipeImport $model) use ($import, $sequence) {
            if ($model->is($import) && $model->wasChanged('status')) {
                $sequence->statuses[] = $model->status;
            }
        });

        return $sequence;
    }
}
