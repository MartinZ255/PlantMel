<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeIngredient>
 */
class RecipeIngredientFactory extends Factory
{
    protected $model = RecipeIngredient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'ingredient_id' => Ingredient::factory(),
            'quantity' => fake()->randomFloat(2, 0.25, 500),
            'unit' => fake()->randomElement(['g', 'kg', 'ml', 'l', 'EL', 'TL', 'Stück', 'Prise']),
            'note' => fake()->optional()->words(3, true),
        ];
    }

    /**
     * Zutat ohne Mengenangabe (Spalten sind nullable).
     */
    public function withoutQuantity(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => null,
            'unit' => null,
        ]);
    }
}
