<?php

namespace App\Services;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Importiert ein Rezept aus einem TikTok-Video.
 *
 * Ablauf: Erst wird die Video-Beschreibung (Caption) per yt-dlp geholt und
 * von Gemini als Text analysiert. Nur wenn das Rezept dort nicht vollständig
 * steht, wird das Video heruntergeladen und per Gemini-Video-Analyse
 * ausgewertet (gesprochener Text + eingeblendete Overlays).
 */
class TikTokRecipeImporter
{
    private const GEMINI_BASE = 'https://generativelanguage.googleapis.com';

    /**
     * Fehlerklassen (Exception-Code), aus denen der Job die generische
     * Nutzermeldung ableitet. Der Exception-Text selbst ist nur fürs Log.
     */
    public const ERROR_NOT_CONFIGURED = 1;

    public const ERROR_DOWNLOAD = 2;

    public const ERROR_ANALYSIS = 3;

    /** Obergrenze für das Video (yt-dlp --max-filesize und Nachprüfung). */
    public const MAX_VIDEO_BYTES = 80 * 1024 * 1024;

    /**
     * yt-dlp-Optionen für jeden Aufruf: keine fremden Konfigurationsdateien
     * laden, keine --exec-Befehle ausführen (belegt mit `yt-dlp --help`,
     * Version 2026.07.04).
     */
    private const YTDLP_SAFE_OPTIONS = ['--ignore-config', '--no-exec'];

    public function __construct(
        #[\SensitiveParameter]
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $ytdlpPath,
    ) {}

    /**
     * @return array{recipe: array, source: string}
     */
    public function import(string $url, ?callable $onProgress = null): array
    {
        // Ohne Schlüssel gar nicht erst yt-dlp starten
        if (trim($this->apiKey) === '') {
            throw new RuntimeException('Gemini-API-Schlüssel ist nicht gesetzt (services.gemini.key).', self::ERROR_NOT_CONFIGURED);
        }

        $progress = $onProgress ?? static fn (string $status) => null;

        // 1) Metadaten (Caption, Titel, Dauer) ohne Video-Download
        $progress('downloading');
        $meta = $this->fetchMetadata($url);

        $caption = trim(($meta['description'] ?? '') ?: ($meta['title'] ?? ''));

        // 2) Caption-Analyse (schnell, wenige Tokens)
        $progress('analyzing');
        if (mb_strlen($caption) >= 40) {
            $recipe = $this->analyzeCaption($caption);

            if ($recipe !== null && ($recipe['is_complete'] ?? false)) {
                return ['recipe' => $this->normalize($recipe, $url, $meta), 'source' => 'caption'];
            }
        }

        // 3) Video-Analyse als Fallback
        $progress('downloading');
        $tempBase = $this->reserveTempBase();
        $geminiFile = null;

        try {
            $videoPath = $this->downloadVideo($url, $tempBase);

            $progress('analyzing');
            $upload = $this->uploadToGemini($videoPath);
            $geminiFile = $upload['name'];
            $this->awaitActive($upload['name'], $upload['file']);
            $recipe = $this->analyzeVideo($upload['uri'], $caption);
        } finally {
            // Datenschutz (F-48) und Aufräumen (F-27): Gemini-Datei und alle
            // lokalen Dateien dieses Imports entfernen – auch im Fehlerfall.
            if ($geminiFile !== null) {
                $this->deleteGeminiFile($geminiFile);
            }
            $this->cleanupTempFiles($tempBase);
        }

        if ($recipe === null) {
            throw new RuntimeException('Gemini konnte kein Rezept aus dem Video extrahieren.', self::ERROR_ANALYSIS);
        }

        return ['recipe' => $this->normalize($recipe, $url, $meta), 'source' => 'video'];
    }

    private function fetchMetadata(string $url): array
    {
        $result = Process::timeout(120)->run([
            $this->ytdlpPath, ...self::YTDLP_SAFE_OPTIONS, '-J', '--no-warnings', '--no-playlist', '--', $url,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException(
                'Video-Metadaten konnten nicht geladen werden: '.mb_substr($result->errorOutput(), 0, 400),
                self::ERROR_DOWNLOAD,
            );
        }

        $meta = json_decode($result->output(), true);

        if (! is_array($meta)) {
            throw new RuntimeException('yt-dlp lieferte keine lesbaren Metadaten.', self::ERROR_DOWNLOAD);
        }

        return $meta;
    }

    /**
     * Legt einen eindeutigen Dateinamen-Stamm im Temp-Verzeichnis fest.
     * Alle Dateien des Downloads (Video, .part, Fragmente) beginnen damit.
     */
    private function reserveTempBase(): string
    {
        $target = tempnam(sys_get_temp_dir(), 'tiktok_');

        if ($target === false) {
            throw new RuntimeException('Temp-Datei konnte nicht angelegt werden.', self::ERROR_DOWNLOAD);
        }

        // yt-dlp hängt selbst die Endung an; wir geben das Muster vor
        @unlink($target);

        return $target;
    }

    private function downloadVideo(string $url, string $tempBase): string
    {
        $result = Process::timeout(240)->run([
            $this->ytdlpPath,
            ...self::YTDLP_SAFE_OPTIONS,
            '-f', 'mp4/best',
            '--max-filesize', '80M',
            '--no-playlist',
            '-o', $tempBase.'.%(ext)s',
            '--',
            $url,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException(
                'Video-Download fehlgeschlagen: '.mb_substr($result->errorOutput(), 0, 400),
                self::ERROR_DOWNLOAD,
            );
        }

        foreach (glob($tempBase.'.*') ?: [] as $file) {
            if (str_ends_with($file, '.part') || str_ends_with($file, '.ytdl') || str_contains($file, '.part-Frag')) {
                continue;
            }

            if (filesize($file) > self::MAX_VIDEO_BYTES) {
                throw new RuntimeException('Video ist größer als 80 MB.', self::ERROR_DOWNLOAD);
            }

            return $file;
        }

        throw new RuntimeException('Video-Download lieferte keine Datei (evtl. über 80 MB?).', self::ERROR_DOWNLOAD);
    }

    /**
     * Entfernt alle lokalen Dateien, die zu diesem Import gehören
     * (fertiges Video, `.part`, `.ytdl`, Fragmente).
     */
    private function cleanupTempFiles(string $tempBase): void
    {
        foreach (glob($tempBase.'*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * @return array{name: string, uri: string, file: array}
     */
    private function uploadToGemini(string $path): array
    {
        $size = filesize($path);
        $mime = 'video/mp4';

        $response = Http::withHeaders([
            'x-goog-api-key' => $this->apiKey,
            'X-Goog-Upload-Protocol' => 'resumable',
            'X-Goog-Upload-Command' => 'start',
            'X-Goog-Upload-Header-Content-Length' => (string) $size,
            'X-Goog-Upload-Header-Content-Type' => $mime,
        ])->post(
            self::GEMINI_BASE.'/upload/v1beta/files',
            ['file' => ['display_name' => 'tiktok-import']]
        );

        $uploadUrl = $response->header('X-Goog-Upload-URL');

        if (! $response->successful() || ! $uploadUrl) {
            throw new RuntimeException('Gemini-Upload konnte nicht gestartet werden: '.mb_substr($response->body(), 0, 300), self::ERROR_ANALYSIS);
        }

        // Video als Stream senden statt komplett in den Speicher zu laden
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Heruntergeladenes Video ist nicht lesbar.', self::ERROR_DOWNLOAD);
        }

        $stream = Utils::streamFor($handle);

        try {
            $upload = Http::withHeaders([
                'X-Goog-Upload-Command' => 'upload, finalize',
                'X-Goog-Upload-Offset' => '0',
            ])->withBody($stream, $mime)
                ->timeout(300)
                ->post($uploadUrl);
        } finally {
            // Handle schließen, damit die Datei (auch unter Windows) gelöscht werden kann
            $stream->close();
        }

        if (! $upload->successful()) {
            throw new RuntimeException('Gemini-Upload fehlgeschlagen: '.mb_substr($upload->body(), 0, 300), self::ERROR_ANALYSIS);
        }

        $file = $upload->json('file');
        $name = is_array($file) ? ($file['name'] ?? null) : null;
        $uri = is_array($file) ? ($file['uri'] ?? null) : null;

        // Der Name wird in URLs eingesetzt (Status, DELETE) – nur das bekannte Format zulassen
        if (! is_string($name) || ! preg_match('#^files/[A-Za-z0-9_-]+$#', $name) || ! is_string($uri) || $uri === '') {
            throw new RuntimeException('Gemini-Upload lieferte keine Datei-Referenz.', self::ERROR_ANALYSIS);
        }

        return ['name' => $name, 'uri' => $uri, 'file' => $file];
    }

    /**
     * Wartet, bis Google das Video verarbeitet hat.
     */
    private function awaitActive(string $name, array $file): void
    {
        $deadline = time() + 180;
        while (($file['state'] ?? '') === 'PROCESSING' && time() < $deadline) {
            sleep(3);
            $file = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->get(self::GEMINI_BASE.'/v1beta/'.$name)
                ->json() ?? [];
        }

        if (($file['state'] ?? '') !== 'ACTIVE') {
            throw new RuntimeException('Gemini hat das Video nicht verarbeitet (Status: '.($file['state'] ?? 'unbekannt').').', self::ERROR_ANALYSIS);
        }
    }

    /**
     * Löscht die hochgeladene Datei bei Gemini. Ein Fehler hier darf den
     * Import nicht scheitern lassen und wird nur geloggt (ohne Schlüssel).
     */
    private function deleteGeminiFile(string $name): void
    {
        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->timeout(30)
                ->delete(self::GEMINI_BASE.'/v1beta/'.$name);

            if (! $response->successful()) {
                Log::warning('TikTok-Import: Gemini-Datei konnte nicht gelöscht werden.', [
                    'file' => $name,
                    'status' => $response->status(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('TikTok-Import: Gemini-Datei konnte nicht gelöscht werden.', [
                'file' => $name,
                'exception' => $e::class,
            ]);
        }
    }

    private function analyzeCaption(string $caption): ?array
    {
        return $this->generate([
            ['text' => $this->prompt()."\n\nHier die Video-Beschreibung:\n\n".$caption],
        ]);
    }

    private function analyzeVideo(string $fileUri, string $caption): ?array
    {
        $parts = [
            ['file_data' => ['file_uri' => $fileUri, 'mime_type' => 'video/mp4']],
            ['text' => $this->prompt()
                .($caption !== '' ? "\n\nZusätzlich die Video-Beschreibung:\n\n".$caption : '')],
        ];

        return $this->generate($parts);
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
Du extrahierst Kochrezepte. Analysiere den Inhalt (Video: gesprochene Sprache UND eingeblendete Texte beachten) und gib das Rezept als JSON zurück:

{
  "is_complete": true/false,  // false, wenn Zutaten oder Schritte fehlen bzw. unvollständig sind
  "name": "Rezeptname",
  "description": "1-2 Sätze Kurzbeschreibung",
  "duration_minutes": Zahl oder null,
  "servings": Zahl oder null,
  "ingredients": [{"amount": "200 g", "name": "Mehl"}],
  "steps": ["Schritt 1 ...", "Schritt 2 ..."],
  "tags": ["vegetarisch"],
  "notes": "Tipps/Varianten oder null"
}

Regeln: Antworte auf Deutsch. Mengen im Feld "amount" (z. B. "2 EL"), Zutat in "name". Schritte als vollständige, knappe Sätze. Keine Halluzinationen - was nicht erkennbar ist, weglassen bzw. is_complete=false setzen.
PROMPT;
    }

    private function generate(array $parts): ?array
    {
        $response = Http::timeout(120)
            ->withHeaders(['x-goog-api-key' => $this->apiKey])
            ->post(
                self::GEMINI_BASE.'/v1beta/models/'.$this->model.':generateContent',
                [
                    'contents' => [['parts' => $parts]],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                        'temperature' => 0.2,
                    ],
                ]
            );

        if (! $response->successful()) {
            throw new RuntimeException('Gemini-Anfrage fehlgeschlagen: '.mb_substr($response->body(), 0, 300), self::ERROR_ANALYSIS);
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text)) {
            return null;
        }

        $data = json_decode($text, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Bringt das Gemini-Ergebnis in die Form, die das RecipeForm erwartet.
     */
    private function normalize(array $recipe, string $url, array $meta): array
    {
        $ingredients = collect($recipe['ingredients'] ?? [])
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['name'] ?? '')) !== '')
            ->map(fn ($row) => [
                'amount' => trim((string) ($row['amount'] ?? '')),
                'name' => trim((string) $row['name']),
            ])
            ->values()
            ->all();

        $steps = collect($recipe['steps'] ?? [])
            ->filter(fn ($step) => is_string($step) && trim($step) !== '')
            ->map(fn ($step) => trim($step))
            ->values()
            ->all();

        $tags = collect($recipe['tags'] ?? [])
            ->filter(fn ($tag) => is_string($tag) && trim($tag) !== '')
            ->map(fn ($tag) => trim($tag))
            ->unique(fn ($tag) => mb_strtolower($tag))
            ->values()
            ->all();

        return [
            'name' => trim((string) ($recipe['name'] ?? ($meta['title'] ?? ''))),
            'description' => trim((string) ($recipe['description'] ?? '')),
            'duration_minutes' => is_numeric($recipe['duration_minutes'] ?? null)
                ? (int) $recipe['duration_minutes']
                : null,
            'servings' => is_numeric($recipe['servings'] ?? null)
                ? (int) $recipe['servings']
                : null,
            'source' => 'TikTok: '.$url,
            'tags' => $tags,
            'ingredients' => $ingredients !== [] ? $ingredients : [['amount' => '', 'name' => '']],
            'steps' => $steps !== [] ? $steps : [''],
            'notes' => trim((string) ($recipe['notes'] ?? '')),
        ];
    }
}
