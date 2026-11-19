<?php

namespace Tests\Feature\Pages;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\RecipeImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Gate C1b: Prop `featuredRecipes` der Closure-Route `landing` (routes/web.php).
 * Bis zu 6 Rezepte MIT Bild, neueste zuerst; Felder id, name, image, categories,
 * duration_minutes. Ohne Rezepte mit Bild: leeres Array.
 */
class LandingFeaturedRecipesTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_without_recipes_with_images_returns_an_empty_list(): void
    {
        // Rezepte ohne Bild zählen nicht
        Recipe::factory()->count(2)->create();

        $this->get(route('landing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Landing')
                ->where('featuredRecipes', [])
                ->has('canRegister')
            );
    }

    public function test_featured_recipes_have_the_expected_structure(): void
    {
        $recipe = Recipe::factory()->create([
            'title' => 'Linsen-Bolognese',
            'duration_minutes' => 35,
        ]);
        $recipe->categories()->attach([
            Category::factory()->create(['name' => 'Abendessen'])->id,
            Category::factory()->create(['name' => 'Vegetarisch'])->id,
        ]);
        RecipeImage::factory()->for($recipe)->create(['image_path' => 'recipes/linsen.jpg']);

        $this->get(route('landing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Landing')
                ->has('featuredRecipes', 1)
                ->where('featuredRecipes.0', function ($first) use ($recipe) {
                    $first = collect($first)->all();
                    $this->assertSame(['id', 'name', 'image', 'categories', 'duration_minutes'], array_keys($first));
                    $this->assertSame($recipe->id, $first['id']);
                    $this->assertSame('Linsen-Bolognese', $first['name']);
                    // gleiche URL-Bildung wie auf der Rezeptliste: Disk "public"
                    $this->assertSame(Storage::disk('public')->url('recipes/linsen.jpg'), $first['image']);
                    $this->assertEqualsCanonicalizing(['Abendessen', 'Vegetarisch'], collect($first['categories'])->all());
                    $this->assertSame(35, $first['duration_minutes']);

                    return true;
                })
            );
    }

    public function test_only_recipes_with_an_image_are_listed(): void
    {
        $withImage = Recipe::factory()->create(['title' => 'Mit Bild']);
        RecipeImage::factory()->for($withImage)->create();
        Recipe::factory()->create(['title' => 'Ohne Bild']);
        // leerer Pfad zählt wie „kein Bild“
        $emptyPath = Recipe::factory()->create(['title' => 'Leerer Pfad']);
        RecipeImage::factory()->for($emptyPath)->create(['image_path' => '']);

        $this->get(route('landing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('featuredRecipes', 1)
                ->where('featuredRecipes.0.name', 'Mit Bild')
            );
    }

    public function test_featured_recipes_are_limited_to_six_newest_first(): void
    {
        $recipes = collect();
        foreach (range(1, 8) as $i) {
            $this->travelTo(now()->startOfDay()->addMinutes($i));
            $recipe = Recipe::factory()->create(['title' => "Rezept {$i}"]);
            RecipeImage::factory()->for($recipe)->create();
            $recipes->push($recipe);
        }
        $this->travelBack();

        // ein neueres Rezept ohne Bild verdrängt keines mit Bild
        $this->travelTo(now()->startOfDay()->addMinutes(30));
        Recipe::factory()->create(['title' => 'Neuestes ohne Bild']);
        $this->travelBack();

        $this->get(route('landing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('featuredRecipes', 6)
                ->where('featuredRecipes', function ($list) {
                    $names = collect($list)->pluck('name')->all();
                    $this->assertSame(
                        ['Rezept 8', 'Rezept 7', 'Rezept 6', 'Rezept 5', 'Rezept 4', 'Rezept 3'],
                        $names,
                    );

                    return true;
                })
            );
    }

    public function test_recipe_without_categories_and_duration_is_serialized_with_empty_values(): void
    {
        $recipe = Recipe::factory()->create(['duration_minutes' => null]);
        RecipeImage::factory()->for($recipe)->create();

        $this->get(route('landing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('featuredRecipes.0.categories', [])
                ->where('featuredRecipes.0.duration_minutes', null)
            );
    }
}
