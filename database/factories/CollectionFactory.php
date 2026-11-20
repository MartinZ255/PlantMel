<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Rezepte hängen über die Pivot-Tabelle collection_recipes an, z. B.
 * Collection::factory()->hasAttached(Recipe::factory()->count(2), [], 'recipes').
 *
 * @extends Factory<Collection>
 */
class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
