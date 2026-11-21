<?php

namespace App\Http\Controllers;

use App\Jobs\ImportTikTokRecipe;
use App\Models\Category;
use App\Models\RecipeImport;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RecipeImportController extends Controller
{
    public const MESSAGE_ALREADY_RUNNING = 'Es läuft bereits ein Import. Bitte warten, bis er abgeschlossen ist.';

    public const MESSAGE_NO_USER_KEY = 'Für den Import brauchst du einen eigenen Gemini-API-Schlüssel. Du kannst ihn in den Einstellungen unter „KI-Import“ hinterlegen.';

    /**
     * Ergebnis eines eigenen, fertigen Imports (`?import=ID`) als
     * Formular-Vorbelegung; importierte Tags werden auf vorhandene
     * Kategorien gemappt (Groß-/Kleinschreibung), damit die Chips aktiv sind.
     * Genutzt von `recipes.create` (Host) und `community.create` (E18).
     *
     * @param  EloquentCollection<int, Category>  $categories
     * @return array<string, mixed>|null
     */
    public static function prefillFor(Request $request, EloquentCollection $categories): ?array
    {
        if (! $request->filled('import')) {
            return null;
        }

        $import = RecipeImport::where('id', $request->integer('import'))
            ->where('user_id', $request->user()->id)
            ->where('status', 'done')
            ->first();
        $importedRecipe = $import?->result;

        if ($importedRecipe && ! empty($importedRecipe['tags'])) {
            $byLower = $categories->keyBy(fn ($cat) => mb_strtolower($cat->name));
            $importedRecipe['tags'] = collect($importedRecipe['tags'])
                ->map(fn ($tag) => $byLower[mb_strtolower($tag)]->name ?? $tag)
                ->values()
                ->all();
        }

        return $importedRecipe;
    }

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

        // E18: Der Server-Schlüssel gilt nur für Hosts. Alle anderen
        // importieren ausschließlich mit ihrem eigenen Gemini-Schlüssel.
        if (! $request->user()->is_host && ! $request->user()->hasGeminiKey()) {
            return response()->json(['message' => self::MESSAGE_NO_USER_KEY], 422);
        }

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
            // Fortschrittsanzeige (Slice 4): `started` = der Job hat begonnen;
            // `updated_at` = letzter Statuswechsel. Zeiten als ISO-8601 in UTC.
            'started' => $recipeImport->status !== 'pending',
            'created_at' => $recipeImport->created_at?->copy()->utc()->toIso8601ZuluString(),
            'updated_at' => $recipeImport->updated_at?->copy()->utc()->toIso8601ZuluString(),
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
