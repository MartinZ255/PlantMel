<?php

namespace Database\Factories;

use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    protected $model = Ingredient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // ingredients.name ist UNIQUE -> eindeutige Namen erzwingen.
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
