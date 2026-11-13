<?php

namespace Tests\Feature\Import;

use App\Jobs\ImportTikTokRecipe;
use App\Models\RecipeImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Import\Concerns\FakesTikTokImport;
use Tests\TestCase;
use Throwable;

/**
 * Regressionstest zu F-27 (vormals bekannter Defekt; auch F-48, F-67):
 * - Rohe Fehlertexte von yt-dlp (stderr) und Gemini (Response-Body) landeten
 *   in recipe_imports.error und gingen über recipeImports.show an den Nutzer.
 * - Zu Gemini hochgeladene Videos wurden nach der Analyse nie gelöscht.
 * - Temp-Dateien (.part) blieben nach fehlgeschlagenem Download liegen.
 * - Ein fehlender API-Schlüssel wurde nicht vorab geprüft.
 *
 * Sollverhalten: generische Nutzermeldung, Rohtext nur im Log; DELETE der
 * Gemini-Datei nach Erfolg und Fehler; Aufräumen im finally.
 */
#[Group('F-27')]
class ImportErrorHandlingTest extends TestCase
{
    use FakesTikTokImport;
    use RefreshDatabase;

    private const MARKER = 'SECRET-DETAIL-PFAD';

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

    public function test_ytdlp_error_details_are_not_exposed_to_user_but_logged(): void
    {
        Log::spy();
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
        $this->assertSame(ImportTikTokRecipe::MESSAGE_DOWNLOAD, $response->json('error'));
        $this->assertStringNotContainsString(self::MARKER, $response->getContent());
        $this->assertStringNotContainsString(self::MARKER, (string) $import->fresh()->error);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []) => $context['import_id'] === $import->id
            && str_contains($context['error'], self::MARKER)
            && ! str_contains(json_encode($context), 'test-key'))->once();
    }

    public function test_gemini_error_details_are_not_exposed_to_user_but_logged(): void
    {
        Log::spy();
        $host = $this->actingAsHost();
        $import = RecipeImport::factory()->for($host)->pending()->create(['url' => self::URL]);

        $this->fakeYtDlp(['description' => self::LONG_CAPTION]);
        Http::fake(['*' => Http::response(
            '{"error":{"code":500,"message":"'.self::MARKER.' internal backend detail test-key"}}',
            500,
        )]);

        $this->runJobIgnoringException($import);

        $response = $this->getJson(route('recipeImports.show', $import))->assertOk();

        $this->assertSame('failed', $response->json('status'));
        $this->assertSame(ImportTikTokRecipe::MESSAGE_ANALYSIS, $response->json('error'));
        $this->assertStringNotContainsString(self::MARKER, $response->getContent());

        // Rohfehler im Log, ein darin enthaltener API-Schlüssel ist geschwärzt
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []) => ($context['import_id'] ?? null) === $import->id
            && str_contains($context['error'], self::MARKER)
            && str_contains($context['error'], '[redacted]')
            && ! str_contains($context['error'], 'test-key'))->once();
    }

    public function test_uploaded_gemini_file_is_deleted_after_video_analysis(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        $this->fakeYtDlp(['title' => 'Kurz', 'description' => '']);
        $this->fakeGeminiVideoFlow(captionRecipe: null, videoRecipe: ['is_complete' => true, 'name' => 'Aus Video']);

        (new ImportTikTokRecipe($import))->handle();

        $this->assertSame('done', $import->fresh()->status);
        $this->assertSame('video', $import->fresh()->source);

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE'
            && $r->url() === self::GEMINI_FILE_URL);
        $this->assertNoImportTempFiles();
    }

    public function test_uploaded_gemini_file_is_deleted_after_failed_video_analysis(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        $this->fakeYtDlp(['title' => 'Kurz', 'description' => '']);
        $this->fakeGeminiVideoFlow(captionRecipe: null, videoRecipe: null, videoGenerateStatus: 500);

        $this->runJobIgnoringException($import);

        $this->assertSame('failed', $import->fresh()->status);
        $this->assertSame(ImportTikTokRecipe::MESSAGE_ANALYSIS, $import->fresh()->error);
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === self::GEMINI_FILE_URL);
        $this->assertNoImportTempFiles();
    }

    public function test_uploaded_gemini_file_is_deleted_when_processing_does_not_finish(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        $this->fakeYtDlp(['title' => 'Kurz', 'description' => '']);
        $this->fakeGeminiVideoFlow(captionRecipe: null, videoRecipe: null, fileState: 'FAILED');

        $this->runJobIgnoringException($import);

        $this->assertSame(ImportTikTokRecipe::MESSAGE_ANALYSIS, $import->fresh()->error);
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === self::GEMINI_FILE_URL);
        Http::assertNotSent(fn (Request $r) => $r->url() === self::GENERATE_URL);
    }

    public function test_failing_delete_does_not_fail_the_import(): void
    {
        Log::spy();
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        $this->fakeYtDlp(['title' => 'Kurz', 'description' => '']);
        $this->fakeGeminiVideoFlow(captionRecipe: null, videoRecipe: ['is_complete' => true, 'name' => 'Aus Video'], deleteStatus: 500);

        (new ImportTikTokRecipe($import))->handle();

        $this->assertSame('done', $import->fresh()->status);
        $this->assertSame('Aus Video', $import->fresh()->result['name']);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []) => str_contains($message, 'Gemini-Datei')
            && $context['file'] === 'files/abc123'
            && ! str_contains(json_encode($context), 'test-key'))->once();
    }

    public function test_temp_files_are_removed_after_failed_download(): void
    {
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        $this->fakeYtDlp(['title' => 'Kurz', 'description' => ''], download: 'fail', stderr: 'ERROR: '.self::MARKER);
        Http::fake();

        $this->runJobIgnoringException($import);

        $this->assertSame('failed', $import->fresh()->status);
        $this->assertSame(ImportTikTokRecipe::MESSAGE_DOWNLOAD, $import->fresh()->error);
        // .part, .part-Frag1 und .ytdl wurden im finally entfernt
        $this->assertNoImportTempFiles();
        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function missingKeys(): array
    {
        return [
            'null' => [null],
            'leer' => [''],
            'Leerzeichen' => ['   '],
        ];
    }

    /**
     * F-67: Fehlender/leerer Schlüssel -> sauberer Abbruch, bevor yt-dlp startet.
     */
    #[DataProvider('missingKeys')]
    public function test_missing_api_key_fails_early_without_starting_ytdlp(?string $key): void
    {
        $this->configureImporter($key);
        $import = RecipeImport::factory()->pending()->create(['url' => self::URL]);
        Http::fake();

        $this->runJobIgnoringException($import);

        $this->assertSame('failed', $import->fresh()->status);
        $this->assertSame(ImportTikTokRecipe::MESSAGE_NOT_CONFIGURED, $import->fresh()->error);
        Process::assertNothingRan();
        Http::assertNothingSent();
    }

    private function runJobIgnoringException(RecipeImport $import): void
    {
        try {
            (new ImportTikTokRecipe($import))->handle();
        } catch (Throwable) {
            // Der Job reicht die Exception weiter (tries = 1, Eintrag in failed_jobs).
        }
    }
}
