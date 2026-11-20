<?php

namespace Tests\Feature\Recipes;

use App\Models\Recipe;
use App\Models\RecipeImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * F-98: Die Rezeptliste lädt je Rezept nur das erste Bild (älteste ID),
 * nicht alle Bilder.
 */
class RecipeIndexFirstImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_shows_first_image_and_loads_only_one_image_per_recipe(): void
    {
        $recipe = Recipe::factory()->create(['title' => 'Mit drei Bildern']);
        $first = RecipeImage::factory()->create(['recipe_id' => $recipe->id, 'image_path' => 'recipes/erstes.jpg']);
        RecipeImage::factory()->count(2)->create(['recipe_id' => $recipe->id]);

        $other = Recipe::factory()->create(['title' => 'Mit zwei Bildern']);
        RecipeImage::factory()->create(['recipe_id' => $other->id, 'image_path' => 'recipes/anderes.jpg']);
        RecipeImage::factory()->create(['recipe_id' => $other->id]);

        $loadedImageRows = 0;
        DB::listen(function ($query) use (&$loadedImageRows) {
            if (str_contains($query->sql, 'recipe_images')) {
                $loadedImageRows++;
            }
        });

        $response = $this->get(route('recipes.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('recipes/Index')
            ->has('recipes', 2)
            ->where('recipes.0.image', Storage::disk('public')->url($first->image_path))
            ->where('recipes.1.image', Storage::disk('public')->url('recipes/anderes.jpg'))
        );

        $this->assertSame(1, $loadedImageRows, 'Bilder sollen in genau einer Abfrage geladen werden.');
    }
}
