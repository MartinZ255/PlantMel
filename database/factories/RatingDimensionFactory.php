<?php

namespace Database\Factories;

use App\Models\RatingDimension;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RatingDimension>
 */
class RatingDimensionFactory extends Factory
{
    protected $model = RatingDimension::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // rating_dimensions.name ist UNIQUE und max. 100 Zeichen.
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
