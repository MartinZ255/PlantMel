<?php

namespace Database\Factories;

use App\Models\RecipeImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Status-Werte laut Migration/Job: pending | downloading | analyzing | done | failed.
 * Spalte "source" (caption | video) wird erst bei "done" gesetzt.
 *
 * @extends Factory<RecipeImport>
 */
class RecipeImportFactory extends Factory
{
    protected $model = RecipeImport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->host(),
            // Offensichtlich fiktive TikTok-URL.
            'url' => 'https://www.tiktok.com/@example/video/'.fake()->numerify('7###############'),
            'status' => 'pending',
            'source' => null,
            'error' => null,
            'result' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'source' => null,
            'error' => null,
            'result' => null,
        ]);
    }

    /**
     * Abgeschlossener Import mit einem result im Format von
     * TikTokRecipeImporter::normalize().
     *
     * @param  'caption'|'video'  $source
     */
    public function done(string $source = 'caption'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'done',
            'source' => $source,
            'error' => null,
            'result' => self::normalizedResult($attributes['url']),
        ]);
    }

    public function failed(?string $error = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'source' => null,
            'error' => $error ?? 'Das Video konnte nicht geladen werden.',
            'result' => null,
        ]);
    }

    /**
     * Struktur wie TikTokRecipeImporter::normalize() sie zurückgibt.
     *
     * @return array<string, mixed>
     */
    public static function normalizedResult(string $url): array
    {
        return [
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(10),
            'duration_minutes' => fake()->numberBetween(10, 60),
            'servings' => fake()->numberBetween(1, 4),
            'source' => 'TikTok: '.$url,
            'tags' => ['Vegan', 'Schnell'],
            'ingredients' => [
                ['amount' => '200 g', 'name' => 'Kichererbsen'],
                ['amount' => '1 EL', 'name' => 'Olivenöl'],
                ['amount' => '', 'name' => 'Salz'],
            ],
            'steps' => [
                'Kichererbsen abgießen und abspülen.',
                'Mit Olivenöl und Salz vermengen.',
                'Im Ofen 20 Minuten rösten.',
            ],
            'notes' => '',
        ];
    }
}
