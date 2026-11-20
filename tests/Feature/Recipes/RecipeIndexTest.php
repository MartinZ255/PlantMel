<?php

namespace Tests\Feature\Recipes;

use App\Models\Category;
use App\Models\RatingDimension;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Models\RecipeRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Charakterisierung der öffentlichen Rezeptliste (RecipeController::index, GET /rezepte).
 */
class RecipeIndexTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    private Recipe $bolognese;

    private Recipe $curry;

    private Recipe $porridge;

    protected function setUp(): void
    {
        parent::setUp();

        $vegan = Category::factory()->create(['name' => 'Vegan']);
        $schnell = Category::factory()->create(['name' => 'Schnell']);
        Category::factory()->create(['name' => 'Dessert']);

        $this->bolognese = Recipe::factory()->create(['title' => 'Linsen-Bolognese', 'duration_minutes' => 45]);
        $this->attachIngredient($this->bolognese, 'Rote Linsen', '200 g');
        $this->attachIngredient($this->bolognese, 'Tomaten', '400 g');
        $this->bolognese->categories()->attach([$vegan->id]);

        $this->curry = Recipe::factory()->create(['title' => 'Tofu-Curry', 'duration_minutes' => null]);
        $this->attachIngredient($this->curry, 'Tofu', '300 g');
        $this->attachIngredient($this->curry, 'Kokosmilch', '400 ml');
        $this->curry->categories()->attach([$vegan->id, $schnell->id]);

        $this->porridge = Recipe::factory()->create(['title' => 'Porridge', 'duration_minutes' => 10]);
        $this->attachIngredient($this->porridge, 'Haferflocken', '80 g');
        $this->attachIngredient($this->porridge, 'Kokosmilch', '200 ml');
        $this->porridge->categories()->attach([$schnell->id]);
    }

    /**
     * @return list<string>
     */
    private function names(array $props): array
    {
        $names = array_column($props['recipes'], 'name');
        sort($names);

        return $names;
    }

    public function test_guest_sees_index_component_with_expected_prop_structure(): void
    {
        $response = $this->get(route('recipes.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('recipes/Index')
            ->has('recipes', 3)
            ->has('recipes.0', fn (Assert $recipe) => $recipe
                ->hasAll(['id', 'name', 'rating', 'tags', 'time', 'image'])
            )
            ->where('tags', ['Dessert', 'Schnell', 'Vegan']) // alphabetisch
            ->has('tagStats', 3)
            ->has('tagStats.0', fn (Assert $stat) => $stat->hasAll(['key', 'label', 'count']))
            ->where('filters', [
                'search' => '',
                'includeIngredients' => [],
                'excludeIngredients' => [],
                'tags' => [],
            ])
            ->where('isHost', false)
        );
    }

    public function test_recipe_entry_maps_title_tags_time_rating_and_image(): void
    {
        Storage::fake('public');
        $dimA = RatingDimension::factory()->create(['name' => 'Geschmack']);
        $dimB = RatingDimension::factory()->create();
        $dimC = RatingDimension::factory()->create();
        foreach ([[$dimA, 4], [$dimB, 5], [$dimC, 5]] as [$dim, $value]) {
            RecipeRating::factory()->create([
                'recipe_id' => $this->bolognese->id,
                'dimension_id' => $dim->id,
                'value' => $value,
            ]);
        }
        RecipeImage::factory()->create(['recipe_id' => $this->bolognese->id, 'image_path' => 'recipes/erstes.jpg']);
        RecipeImage::factory()->create(['recipe_id' => $this->bolognese->id, 'image_path' => 'recipes/zweites.jpg']);

        $props = $this->pageProps($this->get(route('recipes.index')));
        $byName = collect($props['recipes'])->keyBy('name');

        $bolognese = $byName['Linsen-Bolognese'];
        $this->assertSame($this->bolognese->id, $bolognese['id']);
        // E14: nur Geschmack (4); vorher Ø aller Dimensionen 4,67 -> 5
        $this->assertSame(4, $bolognese['rating']);
        $this->assertSame(['Vegan'], $bolognese['tags']);
        $this->assertSame('45 Min', $bolognese['time']);
        $this->assertSame(Storage::disk('public')->url('recipes/erstes.jpg'), $bolognese['image']);

        $curry = $byName['Tofu-Curry'];
        $this->assertSame(0, $curry['rating']); // keine Bewertungen -> 0
        $this->assertSame('–', $curry['time']); // keine Dauer -> Gedankenstrich
        $this->assertNull($curry['image']);
        $this->assertEqualsCanonicalizing(['Vegan', 'Schnell'], $curry['tags']);
    }

    public function test_list_rating_rounds_taste_average_and_ignores_other_dimensions(): void
    {
        $geschmack = RatingDimension::factory()->create(['name' => 'Geschmack']);
        $gesamt = RatingDimension::factory()->create(['name' => 'Gesamt']);
        RecipeRating::factory()->create(['recipe_id' => $this->curry->id, 'dimension_id' => $gesamt->id, 'value' => 5]);
        RecipeRating::factory()->create(['recipe_id' => $this->bolognese->id, 'dimension_id' => $geschmack->id, 'value' => 2]);
        RecipeRating::factory()->create(['recipe_id' => $this->bolognese->id, 'dimension_id' => $gesamt->id, 'value' => 5]);

        $byName = collect($this->pageProps($this->get(route('recipes.index')))['recipes'])->keyBy('name');

        $this->assertSame(2, $byName['Linsen-Bolognese']['rating']);
        $this->assertSame(0, $byName['Tofu-Curry']['rating']); // nur „Gesamt“ -> unbewertet
    }

    public function test_list_without_taste_dimension_shows_all_unrated(): void
    {
        $gesamt = RatingDimension::factory()->create(['name' => 'Gesamt']);
        RecipeRating::factory()->create(['recipe_id' => $this->bolognese->id, 'dimension_id' => $gesamt->id, 'value' => 5]);

        $response = $this->get(route('recipes.index'));

        $response->assertOk();
        $this->assertSame([0], collect($this->pageProps($response)['recipes'])->pluck('rating')->unique()->values()->all());
    }

    public function test_tag_stats_count_recipes_per_category_and_use_name_as_key(): void
    {
        $props = $this->pageProps($this->get(route('recipes.index')));

        $stats = collect($props['tagStats'])->keyBy('label');

        // categories hat keine slug-Spalte -> key fällt auf den Namen zurück.
        $this->assertSame(['key' => 'Vegan', 'label' => 'Vegan', 'count' => 2], $stats['Vegan']);
        $this->assertSame(['key' => 'Schnell', 'label' => 'Schnell', 'count' => 2], $stats['Schnell']);
        $this->assertSame(['key' => 'Dessert', 'label' => 'Dessert', 'count' => 0], $stats['Dessert']);
    }

    public function test_search_matches_title(): void
    {
        $props = $this->pageProps($this->get(route('recipes.index', ['search' => 'Curry'])));

        $this->assertSame(['Tofu-Curry'], $this->names($props));
        $this->assertSame('Curry', $props['filters']['search']);
    }

    public function test_search_matches_ingredient_name(): void
    {
        $props = $this->pageProps($this->get(route('recipes.index', ['search' => 'Linsen'])));
        $this->assertSame(['Linsen-Bolognese'], $this->names($props));

        $props = $this->pageProps($this->get(route('recipes.index', ['search' => 'Kokos'])));
        $this->assertSame(['Porridge', 'Tofu-Curry'], $this->names($props));
    }

    public function test_search_term_is_trimmed(): void
    {
        $props = $this->pageProps($this->get('/rezepte?search='.urlencode('  Tofu  ')));

        $this->assertSame(['Tofu-Curry'], $this->names($props));
        // Laravel-Middleware TrimStrings kürzt den Query-Parameter bereits.
        $this->assertSame('Tofu', $props['filters']['search']);
    }

    public function test_include_ingredients_requires_every_listed_ingredient(): void
    {
        $props = $this->pageProps($this->get(route('recipes.index', [
            'includeIngredients' => ['Kokosmilch'],
        ])));
        $this->assertSame(['Porridge', 'Tofu-Curry'], $this->names($props));

        $props = $this->pageProps($this->get(route('recipes.index', [
            'includeIngredients' => ['Kokosmilch', 'Tofu'],
        ])));
        $this->assertSame(['Tofu-Curry'], $this->names($props));
        $this->assertSame(['Kokosmilch', 'Tofu'], $props['filters']['includeIngredients']);
    }

    public function test_include_ingredients_accepts_a_single_string_and_partial_names(): void
    {
        $props = $this->pageProps($this->get('/rezepte?includeIngredients=Hafer'));

        $this->assertSame(['Porridge'], $this->names($props));
        $this->assertSame(['Hafer'], $props['filters']['includeIngredients']);
    }

    public function test_exclude_ingredients_removes_recipes_containing_any_listed_ingredient(): void
    {
        $props = $this->pageProps($this->get(route('recipes.index', [
            'excludeIngredients' => ['Kokosmilch'],
        ])));
        $this->assertSame(['Linsen-Bolognese'], $this->names($props));

        $props = $this->pageProps($this->get(route('recipes.index', [
            'excludeIngredients' => ['Tofu', 'Linsen'],
        ])));
        $this->assertSame(['Porridge'], $this->names($props));
    }

    public function test_empty_filter_entries_are_dropped(): void
    {
        $props = $this->pageProps($this->get('/rezepte?includeIngredients[]=&excludeIngredients[]=&tags[]='));

        $this->assertCount(3, $props['recipes']);
        $this->assertSame([], $props['filters']['includeIngredients']);
        $this->assertSame([], $props['filters']['excludeIngredients']);
        $this->assertSame([], $props['filters']['tags']);
    }

    public function test_tag_filter_requires_all_selected_tags_with_exact_name(): void
    {
        $props = $this->pageProps($this->get(route('recipes.index', ['tags' => ['Vegan']])));
        $this->assertSame(['Linsen-Bolognese', 'Tofu-Curry'], $this->names($props));

        $props = $this->pageProps($this->get(route('recipes.index', ['tags' => ['Vegan', 'Schnell']])));
        $this->assertSame(['Tofu-Curry'], $this->names($props));

        // exakter Vergleich, kein LIKE
        $props = $this->pageProps($this->get(route('recipes.index', ['tags' => ['Veg']])));
        $this->assertSame([], $props['recipes']);
    }

    public function test_filters_can_be_combined(): void
    {
        $props = $this->pageProps($this->get(route('recipes.index', [
            'search' => 'o',
            'includeIngredients' => ['Kokosmilch'],
            'excludeIngredients' => ['Tofu'],
            'tags' => ['Schnell'],
        ])));

        $this->assertSame(['Porridge'], $this->names($props));
        $this->assertSame([
            'search' => 'o',
            'includeIngredients' => ['Kokosmilch'],
            'excludeIngredients' => ['Tofu'],
            'tags' => ['Schnell'],
        ], $props['filters']);
    }

    public function test_no_match_returns_empty_recipe_list(): void
    {
        $response = $this->get(route('recipes.index', ['search' => 'Gibtsnicht']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('recipes/Index')
            ->has('recipes', 0)
            ->has('tags', 3)
        );
    }

    public function test_is_host_flag_reflects_role(): void
    {
        $this->get(route('recipes.index'))
            ->assertInertia(fn (Assert $page) => $page->where('isHost', false));

        $this->actingAsUser();
        $this->get(route('recipes.index'))
            ->assertInertia(fn (Assert $page) => $page->where('isHost', false));

        $this->actingAsHost();
        $this->get(route('recipes.index'))
            ->assertInertia(fn (Assert $page) => $page->where('isHost', true));
    }
}
