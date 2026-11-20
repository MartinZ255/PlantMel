<?php

namespace Database\Factories;

use App\Models\RatingDimension;
use App\Models\Recipe;
use App\Models\RecipeRating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Unique-Index: (recipe_id, dimension_id). Standardmäßig wird je Bewertung
 * eine neue Dimension angelegt. Mehrere Bewertungen für dasselbe Rezept
 * brauchen verschiedene Dimensionen, z. B. per ->for($recipe)->count(3).
 *
 * @extends Factory<RecipeRating>
 */
class RecipeRatingFactory extends Factory
{
    protected $model = RecipeRating::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'dimension_id' => RatingDimension::factory(),
            'value' => fake()->numberBetween(1, 5),
        ];
    }
}
