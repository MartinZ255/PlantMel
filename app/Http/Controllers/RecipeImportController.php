<?php

namespace App\Http\Controllers;

use App\Jobs\ImportTikTokRecipe;
use App\Models\RecipeImport;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RecipeImportController extends Controller
{
    public const MESSAGE_ALREADY_RUNNING = 'Es läuft bereits ein Import. Bitte warten, bis er abgeschlossen ist.';

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Nur https:// (F-67); Länge passend zur Spalte recipe_imports.url
            'url' => ['required', 'string', 'max:255', 'url', 'regex:/^https:\/\/((www|vm|vt|m)\.)?tiktok\.com\//i'],
        ], [
            'url.regex' => 'Bitte einen gültigen TikTok-Link einfügen.',
            'url.max' => 'Der Link ist zu lang.',
        ]);

        $userId = $request->user()->id;

        // Höchstens ein laufender Import pro Nutzer (F-26). Die Sperre
        // verhindert, dass zwei gleichzeitige Anfragen beide durchkommen.
        $import = Cache::lock('recipe-import-start:'.$userId, 10)->get(function () use ($userId, $validated) {
            if ($this->hasRunningImport($userId)) {
                return null;
            }

            // Veraltete eigene Importe blockieren nicht, werden aber abgeschlossen
            RecipeImport::where('user_id', $userId)
                ->whereIn('status', ImportTikTokRecipe::RUNNING_STATUSES)
                ->where('updated_at', '<=', $this->staleThreshold())
                ->update(['status' => 'failed', 'error' => ImportTikTokRecipe::MESSAGE_TIMEOUT]);

            return RecipeImport::create([
                'user_id' => $userId,
                'url' => $validated['url'],
                'status' => 'pending',
            ]);
        });

        if (! $import instanceof RecipeImport) {
            return response()->json(['message' => self::MESSAGE_ALREADY_RUNNING], 409);
        }

        ImportTikTokRecipe::dispatch($import);

        return response()->json(['id' => $import->id]);
    }

    public function show(Request $request, RecipeImport $recipeImport): JsonResponse
    {
        abort_unless($recipeImport->user_id === $request->user()->id, 403);

        // Hängengebliebene Importe (kein Worker, Worker abgestürzt) als
        // fehlgeschlagen melden und speichern (F-24)
        if ($this->isStale($recipeImport)) {
            $recipeImport->update([
                'status' => 'failed',
                'error' => ImportTikTokRecipe::MESSAGE_TIMEOUT,
            ]);
        }

        return response()->json([
            'id' => $recipeImport->id,
            'status' => $recipeImport->status,
            'source' => $recipeImport->source,
            'error' => $recipeImport->error,
        ]);
    }

    /**
     * Laufend = Status pending/downloading/analyzing und nicht veraltet.
     */
    private function hasRunningImport(int $userId): bool
    {
        return RecipeImport::where('user_id', $userId)
            ->whereIn('status', ImportTikTokRecipe::RUNNING_STATUSES)
            ->where('updated_at', '>', $this->staleThreshold())
            ->exists();
    }

    private function isStale(RecipeImport $import): bool
    {
        return in_array($import->status, ImportTikTokRecipe::RUNNING_STATUSES, true)
            && $import->updated_at !== null
            && $import->updated_at->lte($this->staleThreshold());
    }

    private function staleThreshold(): CarbonInterface
    {
        return now()->subMinutes(ImportTikTokRecipe::STALE_AFTER_MINUTES);
    }
}
