<?php

namespace App\Services;

use App\Models\RecipeImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Entfernt Rezeptbilder vom public-Disk (F-19). Aufrufen erst NACH dem
 * Commit bzw. nach einem Rollback für neu hochgeladene Dateien. Pfade, die
 * noch von einem anderen Datensatz referenziert werden, bleiben erhalten.
 * Fehler werden geloggt und lassen den Request nicht scheitern.
 *
 * E18: aus RecipeController herausgelöst, damit auch Community-Rezepte und
 * die Konto-Löschung dieselbe Logik nutzen.
 */
class RecipeImageFiles
{
    /**
     * @param  array<int, string|null>  $paths
     */
    public static function delete(array $paths): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            try {
                if (RecipeImage::where('image_path', $path)->exists()) {
                    continue;
                }

                if (! Storage::disk('public')->delete($path)) {
                    Log::warning('Rezeptbild konnte nicht gelöscht werden.', ['path' => $path]);
                }
            } catch (Throwable $e) {
                Log::warning('Rezeptbild konnte nicht gelöscht werden.', [
                    'path' => $path,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
