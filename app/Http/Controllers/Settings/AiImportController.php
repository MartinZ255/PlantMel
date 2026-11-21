<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * E18 / ADR-005: Einstellungsseite „KI-Import“ – eigener Gemini-API-
 * Schlüssel für den TikTok-Import.
 *
 * - Gespeichert verschlüsselt (Cast `encrypted`); die Seite erhält nie den
 *   Klartext, nur „hinterlegt, endet auf …“ (letzte vier Zeichen).
 * - Serverseitige Formatprüfung (User::GEMINI_KEY_PATTERN).
 * - „Schlüssel prüfen“ ruft einmal die Modellliste von Gemini ab (in Tests
 *   per Http::fake). Der Schlüssel steht dabei nur im Header, nie im Log.
 */
class AiImportController extends Controller
{
    /** Leichter, kostenloser Aufruf: eine Seite der Modellliste. */
    public const CHECK_URL = 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=1';

    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/AiImport', [
            'hasKey' => $user->hasGeminiKey(),
            'keyHint' => $user->geminiKeyHint(),
            'isHost' => (bool) $user->is_host,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gemini_api_key' => ['required', 'string', 'regex:'.User::GEMINI_KEY_PATTERN],
        ], [
            'gemini_api_key.required' => 'Bitte gib deinen Gemini-API-Schlüssel ein.',
            'gemini_api_key.regex' => 'Das sieht nicht wie ein Gemini-API-Schlüssel aus. Er beginnt mit „AIza“ und hat 39 Zeichen.',
        ]);

        $request->user()->forceFill(['gemini_api_key' => trim($validated['gemini_api_key'])])->save();

        return to_route('settings.ai.edit')->with('success', 'Schlüssel gespeichert. Er wird verschlüsselt aufbewahrt.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['gemini_api_key' => null])->save();

        return to_route('settings.ai.edit')->with('success', 'Schlüssel gelöscht.');
    }

    public function check(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasGeminiKey()) {
            return to_route('settings.ai.edit')->with('error', 'Es ist noch kein Schlüssel hinterlegt.');
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['x-goog-api-key' => $user->gemini_api_key])
                ->get(self::CHECK_URL);
            $ok = $response->successful();
        } catch (Throwable) {
            $ok = false;
        }

        return to_route('settings.ai.edit')->with(
            $ok ? 'success' : 'error',
            $ok
                ? 'Der Schlüssel funktioniert.'
                : 'Gemini hat den Schlüssel nicht akzeptiert oder war nicht erreichbar. Bitte prüfe ihn in Google AI Studio.',
        );
    }
}
