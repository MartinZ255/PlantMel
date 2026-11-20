<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\IngredientAlternative;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Unique-Index: (recipe_id, ingredient_id, alternative_ingredient_id).
 * Standardmäßig werden Rezept, Basiszutat und Alternative neu angelegt,
 * sodass mehrfaches create() nicht kollidiert.
 *
 * @extends Factory<IngredientAlternative>
 */
class IngredientAlternativeFactory extends Factory
{
    protected $model = IngredientAlternative::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'ingredient_id' => Ingredient::factory(),
            'alternative_ingredient_id' => Ingredient::factory(),
            'note' => fake()->optional()->sentence(4),
        ];
    }
}
