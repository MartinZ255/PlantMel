<?php

namespace Tests\Feature\Import;

use App\Jobs\ImportTikTokRecipe;
use App\Models\RecipeImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Charakterisierung Job ImportTikTokRecipe + TikTokRecipeImporter mit
 * Process- und HTTP-Fakes (kein echtes yt-dlp, keine echte Gemini-API).
 */
class ImportTikTokRecipeJobTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://www.tiktok.com/@example/video/7000000000000000001';

    private const GENERATE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent';

    private const UPLOAD_START_URL = 'https://generativelanguage.googleapis.com/upload/v1beta/files';

    private const UPLOAD_SESSION_URL = 'https://generativelanguage.googleapis.com/upload/v1beta/files?upload_id=test-session';

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'gemini-test',
            'services.ytdlp.path' => 'yt-dlp-test',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

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
            'source' => 'TikTok: '.self::URL,
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

        // yt-dlp wird als Array-Aufruf gestartet, URL ist ein eigenes Argument
        Process::assertRanTimes(fn (PendingProcess $p) => $p->command === [
            'yt-dlp-test', '-J', '--no-warnings', '--no-playlist', self::URL,
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

        // Nur ein generateContent-Aufruf (Video), keiner für die Caption
        Http::assertSentCount(3);
        Http::assertNotSent(fn (Request $r) => $r->url() === self::GENERATE_URL
            && ! str_contains(json_encode($r->data()), 'file_data'));
    }

    /**
     * Video-Pfad: Caption unvollständig -> Download per yt-dlp (Fake legt die
     * Datei an), Upload zu Gemini (resumable), Video-Analyse, Temp-Datei wird
     * gelöscht. Ist-Zustand: Die Gemini-Datei wird NICHT gelöscht (F-27, siehe
     * tests/KnownDefects/Backend/ImportErrorHandlingTest).
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

        // Download-Aufruf als Array mit URL als letztem eigenem Argument
        Process::assertRan(function (PendingProcess $p) {
            return is_array($p->command)
                && $p->command[0] === 'yt-dlp-test'
                && array_slice($p->command, 1, 6) === ['-f', 'mp4/best', '--max-filesize', '80M', '--no-playlist', '-o']
                && str_ends_with($p->command[7], '.%(ext)s')
                && $p->command[8] === self::URL
                && count($p->command) === 9;
        });

        // Heruntergeladene Temp-Datei wurde entfernt
        $this->assertNotNull($downloaded->path);
        $this->assertFileDoesNotExist($downloaded->path);

        // Upload-Start mit Header-Schlüssel, Upload ohne Schlüssel, Analyse mit file_data
        Http::assertSent(fn (Request $r) => $r->url() === self::UPLOAD_START_URL
            && $r->header('x-goog-api-key') === ['test-key']
            && $r->header('X-Goog-Upload-Command') === ['start']);
        Http::assertSent(fn (Request $r) => $r->url() === self::UPLOAD_SESSION_URL
            && $r->header('X-Goog-Upload-Command') === ['upload, finalize']
            && $r->body() === 'FAKE-MP4');
        Http::assertSent(fn (Request $r) => $r->url() === self::GENERATE_URL
            && ($r['contents'][0]['parts'][0]['file_data']['file_uri'] ?? null) === 'https://generativelanguage.googleapis.com/v1beta/files/abc123'
            && str_contains($r['contents'][0]['parts'][1]['text'], 'Leckere Bowl'));
        Http::assertSentCount(4);

        // Ist-Zustand F-27: kein DELETE der hochgeladenen Gemini-Datei
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE');

        // Kein Request enthält den Schlüssel in der URL
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'test-key'));
    }

    public function test_ytdlp_failure_marks_import_failed_and_rethrows(): void
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
        // Der Process-Fake hängt an errorOutput einen Zeilenumbruch an.
        $expected = "Video-Metadaten konnten nicht geladen werden: ERROR: Unsupported URL\n";
        $this->assertSame($expected, $thrown->getMessage());

        $import->refresh();
        $this->assertSame('failed', $import->status);
        // Ist-Zustand: roher Tool-Fehlertext landet im Nutzerfeld (F-27)
        $this->assertSame($expected, $import->error);
        $this->assertNull($import->result);
        Http::assertNothingSent();
    }

    public function test_unreadable_ytdlp_output_marks_import_failed(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        Process::fake(['*' => Process::result(output: 'kein json')]);
        Http::fake();

        $this->expectExceptionMessageAndAssertImport(
            $import,
            'yt-dlp lieferte keine lesbaren Metadaten.',
        );
    }

    public function test_gemini_http_500_marks_import_failed(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        $this->fakeYtDlp(['description' => str_repeat('Rezept mit vielen Zutaten. ', 3)]);
        Http::fake(['*' => Http::response('{"error":{"code":500,"message":"internal"}}', 500)]);

        $this->expectExceptionMessageAndAssertImport(
            $import,
            'Gemini-Anfrage fehlgeschlagen: {"error":{"code":500,"message":"internal"}}',
        );
    }

    public function test_error_message_is_truncated_to_900_characters(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        $this->fakeYtDlp(['description' => str_repeat('Rezept mit vielen Zutaten. ', 3)]);
        // Service kürzt den Body auf 300 Zeichen -> Gesamtlänge < 900
        Http::fake(['*' => Http::response(str_repeat('x', 2000), 500)]);

        try {
            (new ImportTikTokRecipe($import))->handle();
            $this->fail('Exception erwartet');
        } catch (RuntimeException) {
        }

        $error = $import->fresh()->error;
        $this->assertSame('Gemini-Anfrage fehlgeschlagen: '.str_repeat('x', 300), $error);
    }

    public function test_failed_method_marks_running_import_as_failed(): void
    {
        $running = RecipeImport::factory()->create(['status' => 'analyzing']);
        (new ImportTikTokRecipe($running))->failed(new RuntimeException('Worker abgestürzt'));
        $this->assertSame('failed', $running->fresh()->status);
        $this->assertSame('Worker abgestürzt', $running->fresh()->error);

        $timeout = RecipeImport::factory()->pending()->create();
        (new ImportTikTokRecipe($timeout))->failed(null);
        $this->assertSame('failed', $timeout->fresh()->status);
        $this->assertSame('Import abgebrochen (Zeitüberschreitung).', $timeout->fresh()->error);
    }

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

    public function test_job_configuration(): void
    {
        $job = new ImportTikTokRecipe(RecipeImport::factory()->create());

        // Ist-Zustand zu F-25: timeout 600 s, ein Versuch
        $this->assertSame(600, $job->timeout);
        $this->assertSame(1, $job->tries);
        $this->assertSame(90, config('queue.connections.database.retry_after'));
    }

    private function expectExceptionMessageAndAssertImport(RecipeImport $import, string $message): void
    {
        try {
            (new ImportTikTokRecipe($import))->handle();
            $this->fail('Exception erwartet');
        } catch (RuntimeException $e) {
            $this->assertSame($message, $e->getMessage());
        }

        $import->refresh();
        $this->assertSame('failed', $import->status);
        $this->assertSame($message, $import->error);
        $this->assertNull($import->result);
    }

    /**
     * Fake für yt-dlp: "-J" liefert Metadaten, der Download-Aufruf legt die
     * Zieldatei gemäß "-o"-Muster an (wie das echte yt-dlp).
     *
     * @param  array<string, mixed>  $meta
     */
    private function fakeYtDlp(array $meta): object
    {
        $downloaded = new class
        {
            public ?string $path = null;
        };

        Process::fake(['*' => function (PendingProcess $process) use ($meta, $downloaded) {
            $command = $process->command;

            if (in_array('-J', $command, true)) {
                return Process::result(output: json_encode($meta));
            }

            $pattern = $command[array_search('-o', $command, true) + 1];
            $path = str_replace('%(ext)s', 'mp4', $pattern);
            file_put_contents($path, 'FAKE-MP4');
            $downloaded->path = $path;
            $this->tempFiles[] = $path;

            return Process::result();
        }]);

        return $downloaded;
    }

    /**
     * @param  array<string, mixed>|null  $captionRecipe
     * @param  array<string, mixed>  $videoRecipe
     */
    private function fakeGeminiVideoFlow(?array $captionRecipe, array $videoRecipe): void
    {
        Http::fake(function (Request $request) use ($captionRecipe, $videoRecipe) {
            if ($request->url() === self::UPLOAD_START_URL) {
                return Http::response([], 200, ['X-Goog-Upload-URL' => self::UPLOAD_SESSION_URL]);
            }

            if ($request->url() === self::UPLOAD_SESSION_URL) {
                return Http::response(['file' => [
                    'name' => 'files/abc123',
                    'uri' => 'https://generativelanguage.googleapis.com/v1beta/files/abc123',
                    'state' => 'ACTIVE',
                ]]);
            }

            if ($request->url() === self::GENERATE_URL) {
                $isVideo = Str::contains(json_encode($request->data()), 'file_data');

                return Http::response($this->geminiText($isVideo ? $videoRecipe : $captionRecipe));
            }

            if ($request->method() === 'DELETE') {
                return Http::response([], 200);
            }

            return Http::response('unerwartet: '.$request->method().' '.$request->url(), 599);
        });
    }

    /**
     * Antwortstruktur von generateContent: candidates.0.content.parts.0.text
     *
     * @param  array<string, mixed>|null  $recipe
     * @return array<string, mixed>
     */
    private function geminiText(?array $recipe): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($recipe)]]]]]];
    }
}
