<?php

namespace Tests\Feature\Import;

use App\Jobs\ImportTikTokRecipe;
use App\Models\RecipeImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\Feature\Import\Concerns\FakesTikTokImport;
use Tests\TestCase;

/**
 * Job ImportTikTokRecipe + TikTokRecipeImporter mit Process- und HTTP-Fakes
 * (kein echtes yt-dlp, keine echte Gemini-API).
 */
class ImportTikTokRecipeJobTest extends TestCase
{
    use FakesTikTokImport;
    use RefreshDatabase;

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

    public function test_caption_path_marks_import_done_with_normalized_result(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        $statuses = [];

        Process::fake(['*' => function (PendingProcess $process) use ($import, &$statuses) {
            $statuses['ytdlp'] = $import->fresh()->status;

            return Process::result(output: json_encode([
                'title' => 'Kichererbsen',
                'description' => 'Geröstete Kichererbsen: 200 g Kichererbsen, 1 EL Öl, Salz. 20 Min backen.',
            ]));
        }]);

        Http::fake(function (Request $request) use ($import, &$statuses) {
            $statuses['gemini'] = $import->fresh()->status;

            return Http::response($this->geminiText([
                'is_complete' => true,
                'name' => '  Geröstete Kichererbsen ',
                'description' => 'Knuspriger Snack.',
                'duration_minutes' => '25',
                'servings' => 'zwei',
                'ingredients' => [
                    ['amount' => ' 200 g ', 'name' => ' Kichererbsen '],
                    ['amount' => '1 EL', 'name' => ''],
                    'kein Array',
                    ['name' => 'Salz'],
                ],
                'steps' => [' Abgießen. ', '', 42, 'Rösten.'],
                'tags' => ['Vegan', 'vegan', ' Snack ', ''],
                'notes' => null,
            ]));
        });

        (new ImportTikTokRecipe($import))->handle();

        $import->refresh();
        $this->assertSame('done', $import->status);
        $this->assertSame('caption', $import->source);
        $this->assertNull($import->error);
        $this->assertSame([
            'name' => 'Geröstete Kichererbsen',
            'description' => 'Knuspriger Snack.',
            'duration_minutes' => 25,
            'servings' => null,
            // F-15: kein Schlüssel `source` mehr im normalisierten Ergebnis
            // (die Spalte recipe_imports.source = caption/video bleibt)
            'tags' => ['Vegan', 'Snack'],
            'ingredients' => [
                ['amount' => '200 g', 'name' => 'Kichererbsen'],
                ['amount' => '', 'name' => 'Salz'],
            ],
            'steps' => ['Abgießen.', 'Rösten.'],
            'notes' => '',
        ], $import->result);

        // Zwischenstände, die der Client per Polling sieht
        $this->assertSame(['ytdlp' => 'downloading', 'gemini' => 'analyzing'], $statuses);

        // F-26: yt-dlp ohne fremde Konfiguration, ohne --exec, "--" direkt vor der URL
        Process::assertRanTimes(fn (PendingProcess $p) => $p->command === [
            'yt-dlp-test', '--ignore-config', '--no-exec', '-J', '--no-warnings', '--no-playlist', '--', self::URL,
        ], 1);

        // Kein Video-Download im Caption-Pfad
        Process::assertDidntRun(fn (PendingProcess $p) => is_array($p->command) && in_array('-o', $p->command, true));

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === self::GENERATE_URL
                && $request->header('x-goog-api-key') === ['test-key']
                && ! str_contains($request->url(), 'test-key')
                && ! str_contains($request->url(), 'key=')
                && str_contains($request['contents'][0]['parts'][0]['text'], 'Geröstete Kichererbsen: 200 g')
                && $request['generationConfig']['response_mime_type'] === 'application/json';
        });
    }

    public function test_caption_shorter_than_40_characters_is_not_analyzed_and_video_path_is_used(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        $this->fakeYtDlp(['title' => 'Kurz', 'description' => '']);
        $this->fakeGeminiVideoFlow(captionRecipe: null, videoRecipe: ['is_complete' => true, 'name' => 'Aus Video']);

        (new ImportTikTokRecipe($import))->handle();

        $import->refresh();
        $this->assertSame('done', $import->status);
        $this->assertSame('video', $import->source);
        $this->assertSame('Aus Video', $import->result['name']);

        // Upload-Start, Upload, Video-Analyse, DELETE – keine Caption-Analyse
        Http::assertSentCount(4);
        Http::assertNotSent(fn (Request $r) => $r->url() === self::GENERATE_URL
            && ! str_contains(json_encode($r->data()), 'file_data'));
    }

    /**
     * Video-Pfad: Caption unvollständig -> Download per yt-dlp (Fake legt die
     * Datei an), Upload zu Gemini (resumable, als Stream), Video-Analyse,
     * DELETE der Gemini-Datei (F-27/F-48), Temp-Datei wird gelöscht.
     */
    public function test_incomplete_caption_falls_back_to_video_analysis(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        $downloaded = $this->fakeYtDlp([
            'title' => 'Bowl',
            'description' => 'Leckere Bowl mit vielen Zutaten, Rezept im Video! #vegan #bowl',
        ]);
        $this->fakeGeminiVideoFlow(
            captionRecipe: ['is_complete' => false, 'name' => 'Bowl'],
            videoRecipe: [
                'is_complete' => true,
                'name' => 'Bunte Bowl',
                'ingredients' => [['amount' => '1', 'name' => 'Avocado']],
                'steps' => ['Alles anrichten.'],
                'tags' => ['Vegan'],
            ],
        );

        (new ImportTikTokRecipe($import))->handle();

        $import->refresh();
        $this->assertSame('done', $import->status);
        $this->assertSame('video', $import->source);
        $this->assertSame('Bunte Bowl', $import->result['name']);
        $this->assertSame([['amount' => '1', 'name' => 'Avocado']], $import->result['ingredients']);

        // Download-Aufruf als Array; "--" unmittelbar vor der URL als letztem Argument
        Process::assertRan(function (PendingProcess $p) {
            return is_array($p->command)
                && $p->command[0] === 'yt-dlp-test'
                && array_slice($p->command, 1, 8) === ['--ignore-config', '--no-exec', '-f', 'mp4/best', '--max-filesize', '80M', '--no-playlist', '-o']
                && str_ends_with($p->command[9], '.%(ext)s')
                && $p->command[10] === '--'
                && $p->command[11] === self::URL
                && count($p->command) === 12;
        });

        // Heruntergeladene Temp-Datei wurde entfernt
        $this->assertNotNull($downloaded->path);
        $this->assertFileDoesNotExist($downloaded->path);
        $this->assertNoImportTempFiles();

        // Upload-Start mit Header-Schlüssel, Upload ohne Schlüssel, Analyse mit file_data
        Http::assertSent(fn (Request $r) => $r->url() === self::UPLOAD_START_URL
            && $r->header('x-goog-api-key') === ['test-key']
            && $r->header('X-Goog-Upload-Command') === ['start']
            && $r->header('X-Goog-Upload-Header-Content-Length') === ['8']);
        Http::assertSent(fn (Request $r) => $r->url() === self::UPLOAD_SESSION_URL
            && $r->header('X-Goog-Upload-Command') === ['upload, finalize']
            && $r->header('x-goog-api-key') === []);
        $this->assertSame('FAKE-MP4', $this->uploadedBody);
        Http::assertSent(fn (Request $r) => $r->url() === self::GENERATE_URL
            && ($r['contents'][0]['parts'][0]['file_data']['file_uri'] ?? null) === self::GEMINI_FILE_URL
            && str_contains($r['contents'][0]['parts'][1]['text'], 'Leckere Bowl'));

        // F-27/F-48: hochgeladene Gemini-Datei wird gelöscht
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE'
            && $r->url() === self::GEMINI_FILE_URL
            && $r->header('x-goog-api-key') === ['test-key']);
        Http::assertSentCount(5);

        // Kein Request enthält den Schlüssel in der URL
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'test-key'));
    }

    public function test_ytdlp_failure_marks_import_failed_with_generic_message_and_rethrows(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        Process::fake(['*' => Process::result(output: '', errorOutput: 'ERROR: Unsupported URL', exitCode: 1)]);
        Http::fake();

        $thrown = null;
        try {
            (new ImportTikTokRecipe($import))->handle();
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'Der Job muss die Exception weiterreichen.');
        // Die Exception (nur Server-Log/failed_jobs) behält den Rohtext
        $this->assertSame("Video-Metadaten konnten nicht geladen werden: ERROR: Unsupported URL\n", $thrown->getMessage());

        $import->refresh();
        $this->assertSame('failed', $import->status);
        // F-27: im Nutzerfeld nur die generische Meldung
        $this->assertSame(ImportTikTokRecipe::MESSAGE_DOWNLOAD, $import->error);
        $this->assertNull($import->result);
        Http::assertNothingSent();
    }

    public function test_unreadable_ytdlp_output_marks_import_failed(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        Process::fake(['*' => Process::result(output: 'kein json')]);
        Http::fake();

        $this->runAndAssertFailed($import, 'yt-dlp lieferte keine lesbaren Metadaten.', ImportTikTokRecipe::MESSAGE_DOWNLOAD);
    }

    public function test_gemini_http_500_marks_import_failed(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        $this->fakeYtDlp(['description' => self::LONG_CAPTION]);
        Http::fake(['*' => Http::response('{"error":{"code":500,"message":"internal"}}', 500)]);

        $this->runAndAssertFailed(
            $import,
            'Gemini-Anfrage fehlgeschlagen: {"error":{"code":500,"message":"internal"}}',
            ImportTikTokRecipe::MESSAGE_ANALYSIS,
        );
    }

    public function test_video_without_recipe_marks_import_failed_with_analysis_message(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        $this->fakeYtDlp(['title' => 'Kurz', 'description' => '']);
        $this->fakeGeminiVideoFlow(captionRecipe: null, videoRecipe: null);

        $this->runAndAssertFailed(
            $import,
            'Gemini konnte kein Rezept aus dem Video extrahieren.',
            ImportTikTokRecipe::MESSAGE_ANALYSIS,
        );

        // Auch ohne Rezept wird die Gemini-Datei gelöscht
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === self::GEMINI_FILE_URL);
    }

    public function test_worker_timeout_is_reported_as_timeout(): void
    {
        $import = RecipeImport::factory()->running('downloading')->create(['url' => self::URL]);
        // Der Worker ruft bei Überschreiten von $timeout failed() mit einer
        // TimeoutExceededException auf (nur mit echtem Worker reproduzierbar).
        (new ImportTikTokRecipe($import))->failed(new TimeoutExceededException('ImportTikTokRecipe has timed out.'));

        $this->assertSame('failed', $import->fresh()->status);
        $this->assertSame(ImportTikTokRecipe::MESSAGE_TIMEOUT, $import->fresh()->error);
    }

    public function test_failed_method_marks_running_import_as_failed_with_generic_message(): void
    {
        $running = RecipeImport::factory()->running()->create();
        (new ImportTikTokRecipe($running))->failed(new RuntimeException('Worker abgestürzt: /var/www/geheim'));
        $this->assertSame('failed', $running->fresh()->status);
        $this->assertSame(ImportTikTokRecipe::MESSAGE_GENERIC, $running->fresh()->error);

        $timeout = RecipeImport::factory()->pending()->create();
        (new ImportTikTokRecipe($timeout))->failed(null);
        $this->assertSame('failed', $timeout->fresh()->status);
        $this->assertSame(ImportTikTokRecipe::MESSAGE_TIMEOUT, $timeout->fresh()->error);
    }

    /**
     * F-25: failed() überschreibt keinen abgeschlossenen Import.
     */
    public function test_failed_method_does_not_overwrite_finished_imports(): void
    {
        $done = RecipeImport::factory()->done()->create();
        $result = $done->result;
        (new ImportTikTokRecipe($done))->failed(new RuntimeException('spät'));
        $this->assertSame('done', $done->fresh()->status);
        $this->assertNull($done->fresh()->error);
        $this->assertSame($result, $done->fresh()->result);

        $failed = RecipeImport::factory()->failed('Ursprünglicher Fehler')->create();
        (new ImportTikTokRecipe($failed))->failed(new RuntimeException('spät'));
        $this->assertSame('Ursprünglicher Fehler', $failed->fresh()->error);
    }

    /**
     * F-25/F-24: Ein Import, der schon beendet (z. B. als veraltet
     * abgebrochen) ist, wird vom Job nicht nachträglich doch gestartet.
     */
    public function test_handle_skips_imports_that_are_no_longer_pending(): void
    {
        $failed = RecipeImport::factory()->failed(ImportTikTokRecipe::MESSAGE_TIMEOUT)->create(['url' => self::URL]);
        $done = RecipeImport::factory()->done()->create(['url' => self::URL]);
        Http::fake();

        (new ImportTikTokRecipe($failed))->handle();
        (new ImportTikTokRecipe($done))->handle();

        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertSame('done', $done->fresh()->status);
        Process::assertNothingRan();
        Http::assertNothingSent();
    }

    /**
     * F-25: retry_after muss größer sein als der Job-Timeout, sonst startet
     * ein zweiter Worker denselben Job, während der erste noch läuft.
     */
    public function test_job_configuration(): void
    {
        $job = new ImportTikTokRecipe(RecipeImport::factory()->create());

        $this->assertSame(600, $job->timeout);
        $this->assertSame(1, $job->tries);
        $this->assertGreaterThan($job->timeout, config('queue.connections.database.retry_after'));
        $this->assertGreaterThan($job->timeout / 60, ImportTikTokRecipe::STALE_AFTER_MINUTES);
    }

    private function runAndAssertFailed(RecipeImport $import, string $rawMessage, string $userMessage): void
    {
        try {
            (new ImportTikTokRecipe($import))->handle();
            $this->fail('Exception erwartet');
        } catch (RuntimeException $e) {
            $this->assertSame($rawMessage, $e->getMessage());
        }

        $import->refresh();
        $this->assertSame('failed', $import->status);
        $this->assertSame($userMessage, $import->error);
        $this->assertNull($import->result);
    }
}
