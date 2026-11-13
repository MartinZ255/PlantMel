<?php

namespace Tests\Feature\Import\Concerns;

use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Gemeinsamer Fake-Aufbau für yt-dlp (Process::fake) und die Gemini-API
 * (Http::fake). Kein echter Prozessstart, kein echter Netzwerkaufruf.
 *
 * Nutzer müssen in tearDown() `cleanupImportTempFiles()` aufrufen.
 */
trait FakesTikTokImport
{
    protected const URL = 'https://www.tiktok.com/@example/video/7000000000000000001';

    protected const GENERATE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent';

    protected const UPLOAD_START_URL = 'https://generativelanguage.googleapis.com/upload/v1beta/files';

    protected const UPLOAD_SESSION_URL = 'https://generativelanguage.googleapis.com/upload/v1beta/files?upload_id=test-session';

    protected const GEMINI_FILE_URL = 'https://generativelanguage.googleapis.com/v1beta/files/abc123';

    protected const LONG_CAPTION = 'Rezept mit vielen Zutaten. Rezept mit vielen Zutaten. Rezept mit vielen Zutaten.';

    /** Dateinamen-Stämme (aus dem -o-Muster), die yt-dlp-Fakes benutzt haben. */
    protected array $ytdlpTempBases = [];

    /** Body des Video-Uploads, im Fake beim Senden gelesen. */
    protected ?string $uploadedBody = null;

    protected function configureImporter(?string $key = 'test-key'): void
    {
        config([
            'services.gemini.key' => $key,
            'services.gemini.model' => 'gemini-test',
            'services.ytdlp.path' => 'yt-dlp-test',
        ]);
    }

    /**
     * Fake für yt-dlp: "-J" liefert Metadaten; der Download-Aufruf legt die
     * Zieldatei gemäß "-o"-Muster an (wie das echte yt-dlp).
     *
     * $download: 'ok' = fertiges Video; 'fail' = Abbruch mit Rest-Dateien
     * (.part, Fragment) und stderr.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function fakeYtDlp(array $meta, string $download = 'ok', string $stderr = 'ERROR: download failed'): object
    {
        $downloaded = new class
        {
            public ?string $path = null;
        };

        Process::fake(['*' => function (PendingProcess $process) use ($meta, $downloaded, $download, $stderr) {
            $command = $process->command;

            if (in_array('-J', $command, true)) {
                return Process::result(output: json_encode($meta));
            }

            $pattern = $command[array_search('-o', $command, true) + 1];
            $base = Str::beforeLast($pattern, '.%(ext)s');
            $this->ytdlpTempBases[] = $base;

            if ($download === 'fail') {
                file_put_contents($base.'.mp4.part', 'HALB');
                file_put_contents($base.'.mp4.part-Frag1', 'FRAG');
                file_put_contents($base.'.mp4.ytdl', '{}');

                return Process::result(errorOutput: $stderr, exitCode: 1);
            }

            $path = $base.'.mp4';
            file_put_contents($path, 'FAKE-MP4');
            $downloaded->path = $path;

            return Process::result();
        }]);

        return $downloaded;
    }

    /**
     * @param  array<string, mixed>|null  $captionRecipe
     * @param  array<string, mixed>|null  $videoRecipe
     */
    protected function fakeGeminiVideoFlow(
        ?array $captionRecipe,
        ?array $videoRecipe,
        int $videoGenerateStatus = 200,
        int $deleteStatus = 200,
        string $fileState = 'ACTIVE',
    ): void {
        Http::fake(function (Request $request) use ($captionRecipe, $videoRecipe, $videoGenerateStatus, $deleteStatus, $fileState) {
            if ($request->url() === self::UPLOAD_START_URL) {
                return Http::response([], 200, ['X-Goog-Upload-URL' => self::UPLOAD_SESSION_URL]);
            }

            if ($request->url() === self::UPLOAD_SESSION_URL) {
                $this->uploadedBody = $request->body();

                return Http::response(['file' => [
                    'name' => 'files/abc123',
                    'uri' => self::GEMINI_FILE_URL,
                    'state' => $fileState,
                ]]);
            }

            if ($request->url() === self::GENERATE_URL) {
                $isVideo = Str::contains(json_encode($request->data()), 'file_data');

                if ($isVideo && $videoGenerateStatus !== 200) {
                    return Http::response('{"error":{"message":"SECRET-GEMINI-DETAIL"}}', $videoGenerateStatus);
                }

                return Http::response($this->geminiText($isVideo ? $videoRecipe : $captionRecipe));
            }

            if ($request->method() === 'DELETE' && $request->url() === self::GEMINI_FILE_URL) {
                return Http::response([], $deleteStatus);
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
    protected function geminiText(?array $recipe): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($recipe)]]]]]];
    }

    /**
     * Prüft, dass von den yt-dlp-Fakes angelegte Dateien wieder weg sind.
     */
    protected function assertNoImportTempFiles(): void
    {
        $this->assertNotEmpty($this->ytdlpTempBases, 'Es gab keinen Download-Aufruf.');

        foreach ($this->ytdlpTempBases as $base) {
            $this->assertSame([], glob($base.'*') ?: [], "Temp-Dateien zu {$base} sind liegengeblieben.");
        }
    }

    protected function cleanupImportTempFiles(): void
    {
        foreach ($this->ytdlpTempBases as $base) {
            foreach (glob($base.'*') ?: [] as $file) {
                @unlink($file);
            }
        }
    }
}
