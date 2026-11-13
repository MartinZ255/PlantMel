<?php

namespace Tests\Feature\Pages;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Recipe;
use App\Models\RecipeImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Charakterisierung der Closure-Route `home` (routes/web.php).
 */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_stats_and_latest_four_recipes(): void
    {
        $user = $this->actingAsUser();
        Collection::factory()->for($user)->count(2)->create();
        Collection::factory()->count(3)->create(); // fremde Sammlungen

        $recipes = collect();
        foreach (range(1, 6) as $i) {
            $this->travelTo(now()->startOfDay()->addMinutes($i));
            $recipes->push(Recipe::factory()->create(['title' => "Rezept {$i}"]));
        }
        $this->travelBack();

        $newest = $recipes->last();
        $newest->categories()->attach([
            Category::factory()->create(['name' => 'Vegan'])->id,
            Category::factory()->create(['name' => 'Schnell'])->id,
        ]);
        RecipeImage::factory()->for($newest)->create(['image_path' => 'recipes/neu.jpg']);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->where('stats', [
                    // Alle Rezepte, aber nur eigene Sammlungen
                    'totalRecipes' => 6,
                    'totalCollections' => 2,
                ])
                ->has('latestRecipes', 4)
                ->where('latestRecipes.0', function ($first) use ($newest) {
                    $first = collect($first)->all();
                    $this->assertSame(['id', 'name', 'categories', 'image'], array_keys($first));
                    $this->assertSame($newest->id, $first['id']);
                    $this->assertSame('Rezept 6', $first['name']);
                    $this->assertEqualsCanonicalizing(['Vegan', 'Schnell'], $first['categories']);
                    // Home nutzt explizit die Disk "public"
                    $this->assertSame(Storage::disk('public')->url('recipes/neu.jpg'), $first['image']);
                    // Disk "public" hängt APP_URL davor (in phpunit.xml fest auf http://localhost)
                    $this->assertSame('http://localhost/storage/recipes/neu.jpg', $first['image']);

                    return true;
                })
                ->where('latestRecipes.1.name', 'Rezept 5')
                ->where('latestRecipes.1.categories', [])
                ->where('latestRecipes.1.image', null)
                ->where('latestRecipes.3.name', 'Rezept 3')
            );
    }

    public function test_home_with_empty_database(): void
    {
        $this->actingAsUser();

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->where('stats.totalRecipes', 0)
                ->where('stats.totalCollections', 0)
                ->has('latestRecipes', 0)
            );
    }
}
