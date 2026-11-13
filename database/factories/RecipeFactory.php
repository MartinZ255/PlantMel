<?php

namespace Database\Factories;

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    protected $model = Recipe::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->optional()->sentence(12),
            'instructions' => self::instructionsText(
                fake()->sentences(fake()->numberBetween(2, 5))
            ),
            'duration_minutes' => fake()->numberBetween(5, 120),
            'servings' => fake()->numberBetween(1, 6),
        ];
    }

    /**
     * Setzt konkrete Zubereitungsschritte (und optional Notizen).
     *
     * @param  list<string>  $steps
     */
    public function withSteps(array $steps, ?string $notes = null): static
    {
        return $this->state(fn (array $attributes) => [
            'instructions' => self::instructionsText($steps, $notes),
        ]);
    }

    /**
     * Erzeugt das Format, in dem die App Anweisungen speichert
     * (vgl. RecipeController::buildInstructionsText):
     * "1. Schritt\n2. Schritt" und optional "Notizen:" gefolgt von Notizzeilen.
     *
     * @param  list<string>  $steps
     */
    public static function instructionsText(array $steps, ?string $notes = null): string
    {
        $lines = [];

        foreach (array_values($steps) as $index => $step) {
            $lines[] = ($index + 1).'. '.trim($step);
        }

        if ($notes !== null && trim($notes) !== '') {
            $lines[] = 'Notizen:';

            foreach (preg_split('/\r\n|\r|\n/', $notes) as $noteLine) {
                $noteLine = trim($noteLine);

                if ($noteLine !== '') {
                    $lines[] = $noteLine;
                }
            }
        }

        return implode("\n", $lines);
    }
}
