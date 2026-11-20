<?php

namespace Tests\Feature\Recipes;

use App\Http\Controllers\RecipeController;
use App\Models\Category;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Gate D2 (F-23): serverseitige Pagination der Rezeptliste (GET /rezepte).
 *
 * Entscheidung zu Seiten hinter der letzten: 302 auf die letzte Seite (Filter
 * bleiben erhalten). Ungültige page-Werte zeigen Seite 1 (Laravel-Standard).
 * Kein Fall endet mit 500.
 */
class RecipeIndexPaginationTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    private function createRecipes(int $count, string $prefix = 'Rezept'): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Recipe::factory()->create(['title' => sprintf('%s %02d', $prefix, $i)]);
        }
    }

    public function test_constant_is_24(): void
    {
        $this->assertSame(24, RecipeController::INDEX_PER_PAGE);
    }

    public function test_first_page_has_24_recipes_and_total_counts_all(): void
    {
        $this->createRecipes(50);

        $this->get(route('recipes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('recipes/Index')
                ->has('recipes', 24)
                ->where('recipes.0.name', 'Rezept 01')
                ->where('recipes.23.name', 'Rezept 24')
                ->where('pagination.currentPage', 1)
                ->where('pagination.lastPage', 3)
                ->where('pagination.perPage', 24)
                ->where('pagination.total', 50)
                ->where('pagination.from', 1)
                ->where('pagination.to', 24)
                ->where('pagination.prevUrl', null)
                ->where('pagination.nextUrl', 'http://localhost/rezepte?page=2')
                ->where('pagination.pages', [
                    ['page' => 1, 'url' => 'http://localhost/rezepte?page=1', 'current' => true],
                    ['page' => 2, 'url' => 'http://localhost/rezepte?page=2', 'current' => false],
                    ['page' => 3, 'url' => 'http://localhost/rezepte?page=3', 'current' => false],
                ])
            );
    }

    public function test_pages_do_not_overlap_and_last_page_holds_the_rest(): void
    {
        $this->createRecipes(50);

        $ids = [];
        foreach ([1, 2, 3] as $n) {
            $props = $this->pageProps($this->get(route('recipes.index', ['page' => $n])));
            $ids = [...$ids, ...array_column($props['recipes'], 'id')];
            $this->assertSame($n, $props['pagination']['currentPage']);
        }

        $this->assertCount(50, $ids);
        $this->assertSame($ids, array_values(array_unique($ids)));
        $this->assertSame(Recipe::orderBy('id')->pluck('id')->all(), $ids);

        $last = $this->pageProps($this->get(route('recipes.index', ['page' => 3])));
        $this->assertCount(2, $last['recipes']);
        $this->assertSame(49, $last['pagination']['from']);
        $this->assertSame(50, $last['pagination']['to']);
        $this->assertNull($last['pagination']['nextUrl']);
        $this->assertSame('http://localhost/rezepte?page=2', $last['pagination']['prevUrl']);
    }

    public function test_single_page_has_no_navigation_links(): void
    {
        $this->createRecipes(24);

        $props = $this->pageProps($this->get(route('recipes.index')));

        $this->assertCount(24, $props['recipes']);
        $this->assertSame(1, $props['pagination']['lastPage']);
        $this->assertSame(24, $props['pagination']['total']);
        $this->assertNull($props['pagination']['prevUrl']);
        $this->assertNull($props['pagination']['nextUrl']);
    }

    public function test_page_window_shows_neighbours_and_gaps(): void
    {
        $this->createRecipes(24 * 9 + 1); // 10 Seiten

        $pages = $this->pageProps($this->get(route('recipes.index', ['page' => 5])))['pagination']['pages'];

        $this->assertSame([1, null, 4, 5, 6, null, 10], array_column($pages, 'page'));
        $this->assertSame([5], array_column(array_filter($pages, fn ($p) => $p['current']), 'page'));
        $this->assertNull($pages[1]['url']);

        // Lücke von genau einer Seite wird als Seite gezeigt (kein „…“ für eine Zahl)
        $pages = $this->pageProps($this->get(route('recipes.index', ['page' => 3])))['pagination']['pages'];
        $this->assertSame([1, 2, 3, 4, null, 10], array_column($pages, 'page'));
    }

    public function test_filters_are_kept_in_all_page_urls(): void
    {
        $vegan = Category::factory()->create(['name' => 'Vegan']);
        for ($i = 1; $i <= 30; $i++) {
            $recipe = Recipe::factory()->create(['title' => sprintf('Linsen %02d', $i)]);
            $this->attachIngredient($recipe, 'Rote Linsen', '100 g');
            $recipe->categories()->attach($vegan);
        }
        // passt nicht zu den Filtern (keine Linsen, kein Tag)
        $this->createRecipes(10, 'Anderes');

        $query = [
            'search' => 'Linsen',
            'includeIngredients' => ['Linsen'],
            'excludeIngredients' => ['Tofu'],
            'tags' => ['Vegan'],
        ];

        $props = $this->pageProps($this->get(route('recipes.index', $query)));

        $this->assertSame(30, $props['pagination']['total']);
        $this->assertSame(2, $props['pagination']['lastPage']);

        $next = $props['pagination']['nextUrl'];
        parse_str((string) parse_url($next, PHP_URL_QUERY), $nextQuery);
        $this->assertSame([...$query, 'page' => '2'], $nextQuery);

        foreach ($props['pagination']['pages'] as $item) {
            parse_str((string) parse_url($item['url'], PHP_URL_QUERY), $q);
            $this->assertSame($query['tags'], $q['tags']);
            $this->assertSame($query['search'], $q['search']);
        }
    }

    public function test_filters_and_page_combined_return_the_filtered_second_page(): void
    {
        $vegan = Category::factory()->create(['name' => 'Vegan']);
        for ($i = 1; $i <= 30; $i++) {
            $recipe = Recipe::factory()->create(['title' => sprintf('Vegan %02d', $i)]);
            $recipe->categories()->attach($vegan);
        }
        $this->createRecipes(30, 'Anderes');

        $this->get(route('recipes.index', ['tags' => ['Vegan'], 'page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('recipes', 6)
                ->where('recipes.0.name', 'Vegan 25')
                ->where('recipes.5.name', 'Vegan 30')
                ->where('pagination.currentPage', 2)
                ->where('pagination.total', 30)
                ->where('filters.tags', ['Vegan'])
            );
    }

    public function test_page_beyond_last_redirects_to_last_page_keeping_filters(): void
    {
        $this->createRecipes(30);

        $location = $this->get('/rezepte?search=Rezept&excludeIngredients[]=Tofu&page=7')
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('http://localhost/rezepte?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame(['search' => 'Rezept', 'excludeIngredients' => ['Tofu'], 'page' => '2'], $query);

        // Ziel der Umleitung ist gültig (kein Kreislauf)
        $this->get($location)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pagination.currentPage', 2)->has('recipes', 6));

        // größte Ganzzahl: kein OFFSET-Überlauf, sondern Umleitung
        $this->get('/rezepte?page='.PHP_INT_MAX)
            ->assertRedirect('http://localhost/rezepte?page=2');
    }

    public function test_page_beyond_last_without_any_match_shows_empty_first_page(): void
    {
        $this->createRecipes(3);

        $this->get('/rezepte?search=Gibtsnicht&page=4')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('recipes', 0)
                ->where('pagination.currentPage', 1)
                ->where('pagination.lastPage', 1)
                ->where('pagination.total', 0)
                ->where('pagination.from', null)
                ->where('pagination.to', null)
            );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidPageValues(): array
    {
        return [
            'null' => ['page=0'],
            'negativ' => ['page=-3'],
            'Text' => ['page=abc'],
            'Komma' => ['page=1.5'],
            'leer' => ['page='],
            'Array' => ['page[]=2'],
            'verschachtelt' => ['page[a][b]=2'],
            'Überlauf' => ['page=99999999999999999999999'],
        ];
    }

    #[DataProvider('invalidPageValues')]
    public function test_invalid_page_value_shows_first_page_instead_of_server_error(string $query): void
    {
        $this->createRecipes(30);

        $this->get('/rezepte?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('recipes', 24)
                ->where('recipes.0.name', 'Rezept 01')
                ->where('pagination.currentPage', 1)
                ->where('pagination.total', 30)
            );
    }

    public function test_partial_reload_of_list_props_skips_tags_and_shared_collections(): void
    {
        $this->actingAsUser();
        $this->createRecipes(30);

        $version = $this->get(route('recipes.index'))->viewData('page')['version'];

        $response = $this->get(route('recipes.index', ['page' => 2]), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
            'X-Inertia-Partial-Component' => 'recipes/Index',
            'X-Inertia-Partial-Data' => 'recipes,pagination,filters',
        ]);

        $response->assertOk();
        $props = $response->json('props');
        $this->assertSame(['errors', 'filters', 'pagination', 'recipes'], collect(array_keys($props))->sort()->values()->all());
        $this->assertCount(6, $props['recipes']);
    }
}
