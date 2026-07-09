<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;

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

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $ytdlpPath,
    ) {}

    /**
     * @return array{recipe: array, source: string}
     */
    public function import(string $url, ?callable $onProgress = null): array
    {
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
        $videoPath = $this->downloadVideo($url);

        try {
            $progress('analyzing');
            $fileUri = $this->uploadToGemini($videoPath);
            $recipe = $this->analyzeVideo($fileUri, $caption);
        } finally {
            @unlink($videoPath);
        }

        if ($recipe === null) {
            throw new RuntimeException('Gemini konnte kein Rezept aus dem Video extrahieren.');
        }

        return ['recipe' => $this->normalize($recipe, $url, $meta), 'source' => 'video'];
    }

    private function fetchMetadata(string $url): array
    {
        $result = Process::timeout(120)->run([
            $this->ytdlpPath, '-J', '--no-warnings', '--no-playlist', $url,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException(
                'Video-Metadaten konnten nicht geladen werden: ' . mb_substr($result->errorOutput(), 0, 400)
            );
        }

        $meta = json_decode($result->output(), true);

        if (! is_array($meta)) {
            throw new RuntimeException('yt-dlp lieferte keine lesbaren Metadaten.');
        }

        return $meta;
    }

    private function downloadVideo(string $url): string
    {
        $target = tempnam(sys_get_temp_dir(), 'tiktok_');
        // yt-dlp hängt selbst die Endung an; wir geben das Muster vor
        @unlink($target);
        $pattern = $target . '.%(ext)s';

        $result = Process::timeout(240)->run([
            $this->ytdlpPath,
            '-f', 'mp4/best',
            '--max-filesize', '80M',
            '--no-playlist',
            '-o', $pattern,
            $url,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException(
                'Video-Download fehlgeschlagen: ' . mb_substr($result->errorOutput(), 0, 400)
            );
        }

        foreach (glob($target . '.*') as $file) {
            return $file;
        }

        throw new RuntimeException('Video-Download lieferte keine Datei (evtl. über 80 MB?).');
    }

    private function uploadToGemini(string $path): string
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
            self::GEMINI_BASE . '/upload/v1beta/files',
            ['file' => ['display_name' => 'tiktok-import']]
        );

        $uploadUrl = $response->header('X-Goog-Upload-URL');

        if (! $response->successful() || ! $uploadUrl) {
            throw new RuntimeException('Gemini-Upload konnte nicht gestartet werden: ' . mb_substr($response->body(), 0, 300));
        }

        $upload = Http::withHeaders([
            'X-Goog-Upload-Command' => 'upload, finalize',
            'X-Goog-Upload-Offset' => '0',
        ])->withBody(file_get_contents($path), $mime)
            ->timeout(300)
            ->post($uploadUrl);

        if (! $upload->successful()) {
            throw new RuntimeException('Gemini-Upload fehlgeschlagen: ' . mb_substr($upload->body(), 0, 300));
        }

        $file = $upload->json('file');
        $name = $file['name'] ?? null;
        $uri = $file['uri'] ?? null;

        if (! $name || ! $uri) {
            throw new RuntimeException('Gemini-Upload lieferte keine Datei-Referenz.');
        }

        // Warten, bis Google das Video verarbeitet hat
        $deadline = time() + 180;
        while (($file['state'] ?? '') === 'PROCESSING' && time() < $deadline) {
            sleep(3);
            $file = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->get(self::GEMINI_BASE . '/v1beta/' . $name)
                ->json();
        }

        if (($file['state'] ?? '') !== 'ACTIVE') {
            throw new RuntimeException('Gemini hat das Video nicht verarbeitet (Status: ' . ($file['state'] ?? 'unbekannt') . ').');
        }

        return $uri;
    }

    private function analyzeCaption(string $caption): ?array
    {
        return $this->generate([
            ['text' => $this->prompt() . "\n\nHier die Video-Beschreibung:\n\n" . $caption],
        ]);
    }

    private function analyzeVideo(string $fileUri, string $caption): ?array
    {
        $parts = [
            ['file_data' => ['file_uri' => $fileUri, 'mime_type' => 'video/mp4']],
            ['text' => $this->prompt()
                . ($caption !== '' ? "\n\nZusätzlich die Video-Beschreibung:\n\n" . $caption : '')],
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
            self::GEMINI_BASE . '/v1beta/models/' . $this->model . ':generateContent',
            [
                'contents' => [['parts' => $parts]],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => 0.2,
                ],
            ]
        );

        if (! $response->successful()) {
            throw new RuntimeException('Gemini-Anfrage fehlgeschlagen: ' . mb_substr($response->body(), 0, 300));
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
            'source' => 'TikTok: ' . $url,
            'tags' => $tags,
            'ingredients' => $ingredients !== [] ? $ingredients : [['amount' => '', 'name' => '']],
            'steps' => $steps !== [] ? $steps : [''],
            'notes' => trim((string) ($recipe['notes'] ?? '')),
        ];
    }
}
