<?php

namespace App\Jobs;

use App\Models\RecipeImport;
use App\Services\TikTokRecipeImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportTikTokRecipe implements ShouldQueue
{
    use Queueable;

    /** Status, in denen ein Import als "laufend" gilt. */
    public const RUNNING_STATUSES = ['pending', 'downloading', 'analyzing'];

    /** Endzustände – werden nie mehr überschrieben. */
    public const FINAL_STATUSES = ['done', 'failed'];

    /**
     * Ein laufender Import ohne Fortschritt (updated_at) seit so vielen
     * Minuten gilt als hängengeblieben (F-24). Muss deutlich über $timeout
     * liegen (600 s), damit ein normal laufender Job nie betroffen ist.
     */
    public const STALE_AFTER_MINUTES = 15;

    // Generische, deutsche Nutzermeldungen (F-27). Rohfehler nur im Log.
    public const MESSAGE_DOWNLOAD = 'Das Video konnte nicht geladen werden.';

    public const MESSAGE_ANALYSIS = 'Die Analyse ist fehlgeschlagen.';

    public const MESSAGE_TIMEOUT = 'Zeitüberschreitung beim Import.';

    public const MESSAGE_NOT_CONFIGURED = 'Import ist nicht konfiguriert.';

    public const MESSAGE_GENERIC = 'Der Import ist fehlgeschlagen.';

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public readonly RecipeImport $import,
    ) {}

    public function handle(): void
    {
        // Nur einen noch nicht begonnenen Import bearbeiten. Wurde er z. B.
        // als veraltet abgebrochen, während er in der Queue wartete, nicht
        // nachträglich doch noch starten (Status nur vorwärts, F-25).
        $this->import->refresh();
        if ($this->import->status !== 'pending') {
            return;
        }

        $importer = new TikTokRecipeImporter(
            apiKey: (string) config('services.gemini.key'),
            model: (string) config('services.gemini.model'),
            ytdlpPath: (string) config('services.ytdlp.path'),
        );

        try {
            $outcome = $importer->import(
                $this->import->url,
                fn (string $status) => $this->transition(['status' => $status]),
            );

            $this->transition([
                'status' => 'done',
                'source' => $outcome['source'],
                'result' => $outcome['recipe'],
                'error' => null,
            ]);
        } catch (Throwable $e) {
            $this->logFailure($e);

            $this->transition([
                'status' => 'failed',
                'error' => self::userMessageFor($e),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        // Falls der Job außerhalb von handle() scheitert (z. B. Timeout),
        // darf der Import nicht ewig auf "analyzing" stehen bleiben
        $this->import->refresh();

        if (! in_array($this->import->status, self::FINAL_STATUSES, true)) {
            if ($exception !== null) {
                $this->logFailure($exception);
            }

            $this->import->update([
                'status' => 'failed',
                'error' => $exception ? self::userMessageFor($exception) : self::MESSAGE_TIMEOUT,
            ]);
        }
    }

    /**
     * Ordnet eine Exception einer generischen Nutzermeldung zu.
     */
    public static function userMessageFor(Throwable $e): string
    {
        if ($e instanceof ProcessTimedOutException) {
            return self::MESSAGE_TIMEOUT;
        }

        if ($e instanceof ConnectionException) {
            return str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'cURL error 28')
                ? self::MESSAGE_TIMEOUT
                : self::MESSAGE_ANALYSIS;
        }

        // Job-Timeout bzw. zu viele Versuche (vom Worker gemeldet);
        // TimeoutExceededException erweitert MaxAttemptsExceededException
        if ($e instanceof MaxAttemptsExceededException) {
            return self::MESSAGE_TIMEOUT;
        }

        return match ($e->getCode()) {
            TikTokRecipeImporter::ERROR_NOT_CONFIGURED => self::MESSAGE_NOT_CONFIGURED,
            TikTokRecipeImporter::ERROR_DOWNLOAD => self::MESSAGE_DOWNLOAD,
            TikTokRecipeImporter::ERROR_ANALYSIS => self::MESSAGE_ANALYSIS,
            default => self::MESSAGE_GENERIC,
        };
    }

    /**
     * Ändert den Import nur, solange er keinen Endzustand erreicht hat.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function transition(array $attributes): void
    {
        $this->import->refresh();

        if (in_array($this->import->status, self::FINAL_STATUSES, true)) {
            return;
        }

        $this->import->update($attributes);
    }

    /**
     * Rohfehler (yt-dlp-stderr, Gemini-Body) nur ins Server-Log, nie an den Client.
     */
    private function logFailure(Throwable $e): void
    {
        $message = mb_substr($e->getMessage(), 0, 2000);
        $key = (string) config('services.gemini.key');

        if ($key !== '') {
            $message = str_replace($key, '[redacted]', $message);
        }

        Log::warning('TikTok-Import fehlgeschlagen.', [
            'import_id' => $this->import->id,
            'exception' => $e::class,
            'code' => $e->getCode(),
            'error' => $message,
        ]);
    }
}
