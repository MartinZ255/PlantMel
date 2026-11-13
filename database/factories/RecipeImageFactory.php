<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Erzeugt nur den Datenbankeintrag; es wird KEINE Datei angelegt.
 * Tests, die die Datei brauchen, nutzen Storage::fake('public') und legen sie selbst an.
 *
 * @extends Factory<RecipeImage>
 */
class RecipeImageFactory extends Factory
{
    protected $model = RecipeImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            // Gleiches Schema wie $file->store('recipes', 'public') im RecipeController.
            'image_path' => 'recipes/'.Str::random(40).'.jpg',
        ];
    }
}
