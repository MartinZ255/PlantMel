<?php

namespace App\Jobs;

use App\Models\RecipeImport;
use App\Services\TikTokRecipeImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ImportTikTokRecipe implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public readonly RecipeImport $import,
    ) {}

    public function handle(): void
    {
        $importer = new TikTokRecipeImporter(
            apiKey: (string) config('services.gemini.key'),
            model: (string) config('services.gemini.model'),
            ytdlpPath: (string) config('services.ytdlp.path'),
        );

        try {
            $outcome = $importer->import(
                $this->import->url,
                fn (string $status) => $this->import->update(['status' => $status]),
            );

            $this->import->update([
                'status' => 'done',
                'source' => $outcome['source'],
                'result' => $outcome['recipe'],
                'error' => null,
            ]);
        } catch (Throwable $e) {
            $this->import->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 900),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        // Falls der Job außerhalb von handle() scheitert (z. B. Timeout),
        // darf der Import nicht ewig auf "analyzing" stehen bleiben
        $this->import->refresh();

        if (! in_array($this->import->status, ['done', 'failed'], true)) {
            $this->import->update([
                'status' => 'failed',
                'error' => $exception
                    ? mb_substr($exception->getMessage(), 0, 900)
                    : 'Import abgebrochen (Zeitüberschreitung).',
            ]);
        }
    }
}
