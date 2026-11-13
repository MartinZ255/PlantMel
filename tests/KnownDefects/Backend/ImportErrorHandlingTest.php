<?php

namespace Tests\KnownDefects\Backend;

use App\Jobs\ImportTikTokRecipe;
use App\Models\RecipeImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Throwable;

/**
 * Bekannter Defekt F-27 (Import-Aufräumen und Fehlertexte):
 * - Rohe Fehlertexte von yt-dlp (stderr, bis 400 Zeichen) und Gemini
 *   (Response-Body, bis 300 Zeichen) landen in recipe_imports.error und werden
 *   über recipeImports.show an den Nutzer ausgeliefert
 *   (TikTokRecipeImporter.php, ImportTikTokRecipe.php, RecipeImportController::show).
 * - Zu Gemini hochgeladene Videos werden nach der Analyse nie gelöscht.
 *
 * Sollverhalten: Der Nutzer erhält eine generische Meldung ohne Tool-Details;
 * nach erfolgreicher Videoanalyse wird ein DELETE auf die Gemini-Datei gesendet.
 */
#[Group('known-defect')]
#[Group('F-27')]
class ImportErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://www.tiktok.com/@example/video/7000000000000000001';

    private const MARKER = 'SECRET-DETAIL-PFAD';

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

    public function test_ytdlp_error_details_are_not_exposed_to_user(): void
    {
        $host = $this->actingAsHost();
        $import = RecipeImport::factory()->for($host)->pending()->create(['url' => self::URL]);

        Process::fake(['*' => Process::result(
            errorOutput: 'ERROR: [TikTok] '.self::MARKER.' /var/www/plantmel/storage/cookies.txt not readable',
            exitCode: 1,
        )]);
        Http::fake();

        $this->runJobIgnoringException($import);

        $response = $this->getJson(route('recipeImports.show', $import))->assertOk();

        $this->assertSame('failed', $response->json('status'));
        $this->assertNotEmpty($response->json('error'));
        $this->assertStringNotContainsString(self::MARKER, (string) $response->json('error'));
    }

    public function test_gemini_error_details_are_not_exposed_to_user(): void
    {
        $host = $this->actingAsHost();
        $import = RecipeImport::factory()->for($host)->pending()->create(['url' => self::URL]);

        $this->fakeYtDlp(['description' => str_repeat('Rezept mit vielen Zutaten. ', 3)]);
        Http::fake(['*' => Http::response(
            '{"error":{"code":500,"message":"'.self::MARKER.' internal backend detail"}}',
            500,
        )]);

        $this->runJobIgnoringException($import);

        $response = $this->getJson(route('recipeImports.show', $import))->assertOk();

        $this->assertSame('failed', $response->json('status'));
        $this->assertNotEmpty($response->json('error'));
        $this->assertStringNotContainsString(self::MARKER, (string) $response->json('error'));
    }

    public function test_uploaded_gemini_file_is_deleted_after_video_analysis(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        $this->fakeYtDlp(['title' => 'Kurz', 'description' => '']);

        Http::fake(function (Request $request) {
            $url = $request->url();

            if ($url === 'https://generativelanguage.googleapis.com/upload/v1beta/files') {
                return Http::response([], 200, ['X-Goog-Upload-URL' => 'https://generativelanguage.googleapis.com/upload/v1beta/files?upload_id=test-session']);
            }

            if (str_starts_with($url, 'https://generativelanguage.googleapis.com/upload/v1beta/files?upload_id=')) {
                return Http::response(['file' => [
                    'name' => 'files/abc123',
                    'uri' => 'https://generativelanguage.googleapis.com/v1beta/files/abc123',
                    'state' => 'ACTIVE',
                ]]);
            }

            if (str_ends_with($url, ':generateContent')) {
                return Http::response(['candidates' => [['content' => ['parts' => [[
                    'text' => json_encode(['is_complete' => true, 'name' => 'Aus Video']),
                ]]]]]]);
            }

            if ($request->method() === 'DELETE') {
                return Http::response([], 200);
            }

            return Http::response('unerwartet', 599);
        });

        (new ImportTikTokRecipe($import))->handle();

        $this->assertSame('done', $import->fresh()->status);
        $this->assertSame('video', $import->fresh()->source);

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE'
            && $r->url() === 'https://generativelanguage.googleapis.com/v1beta/files/abc123');
    }

    private function runJobIgnoringException(RecipeImport $import): void
    {
        try {
            (new ImportTikTokRecipe($import))->handle();
        } catch (Throwable) {
            // Ist-Zustand: der Job reicht die Exception weiter (tries = 1).
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function fakeYtDlp(array $meta): void
    {
        Process::fake(['*' => function (PendingProcess $process) use ($meta) {
            $command = $process->command;

            if (in_array('-J', $command, true)) {
                return Process::result(output: json_encode($meta));
            }

            $pattern = $command[array_search('-o', $command, true) + 1];
            $path = str_replace('%(ext)s', 'mp4', $pattern);
            file_put_contents($path, 'FAKE-MP4');
            $this->tempFiles[] = $path;

            return Process::result();
        }]);
    }
}
