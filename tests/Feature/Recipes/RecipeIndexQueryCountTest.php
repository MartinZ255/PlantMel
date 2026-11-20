<?php

namespace Tests\Feature\Recipes;

use App\Models\Category;
use App\Models\RatingDimension;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Models\RecipeRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Regressionstest F-23 (Rezeptliste, Gate D2): Die Anzahl der Queries für
 * recipes.index hängt nicht von der Anzahl der Rezepte ab, und die Liste lädt
 * keine Zutaten mehr per Eager Loading (die Karten zeigen keine Zutaten).
 */
#[Group('F-23')]
class RecipeIndexQueryCountTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    /**
     * Erwartete Abfragen als Gast, gemessen in Gate D2: vorher 9 (ebenfalls
     * konstant, aber alle Rezepte samt recipe_ingredients/ingredients), jetzt 8
     * (Geschmacks-Dimension, count, eine Seite Rezepte, Kategorien, Bewertungen,
     * Bilder, Tag-Liste, Tag-Statistik).
     */
    private const EXPECTED_GUEST_QUERIES = 8;

    protected function setUp(): void
    {
        parent::setUp();

        RatingDimension::factory()->create(['name' => 'Geschmack']);
        Category::factory()->create(['name' => 'Vegan']);
    }

    private function seedRecipes(int $count): void
    {
        $vegan = Category::where('name', 'Vegan')->firstOrFail();
        $taste = RatingDimension::where('name', 'Geschmack')->firstOrFail();

        Recipe::factory()->count($count)->create()->each(function (Recipe $recipe) use ($vegan, $taste): void {
            $this->attachIngredient($recipe, 'Zutat '.$recipe->id, '1 Stk');
            $recipe->categories()->attach($vegan);
            RecipeImage::factory()->create(['recipe_id' => $recipe->id]);
            RecipeRating::factory()->create(['recipe_id' => $recipe->id, 'dimension_id' => $taste->id, 'value' => 4]);
        });
    }

    /**
     * @return array{count: int, sql: list<string>}
     */
    private function measure(string $uri): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get($uri)->assertOk();

        $log = DB::getQueryLog();
        DB::disableQueryLog();
        DB::flushQueryLog();

        return ['count' => count($log), 'sql' => array_column($log, 'query')];
    }

    public function test_query_count_is_constant_regardless_of_recipe_count(): void
    {
        $this->seedRecipes(3);
        $few = $this->measure('/rezepte');

        $this->seedRecipes(57); // 60 Rezepte, also drei Seiten
        $many = $this->measure('/rezepte');
        $lastPage = $this->measure('/rezepte?page=3');

        $this->assertSame(
            $few['count'],
            $many['count'],
            "recipes.index: {$few['count']} Queries bei 3 Rezepten, {$many['count']} bei 60 (F-23).\n".implode("\n", $many['sql']),
        );
        $this->assertSame($few['count'], $lastPage['count']);
        $this->assertSame(self::EXPECTED_GUEST_QUERIES, $many['count'], implode("\n", $many['sql']));
    }

    public function test_query_count_is_constant_with_filters(): void
    {
        $this->seedRecipes(3);
        $uri = '/rezepte?search=Zutat&includeIngredients[]=Zutat&excludeIngredients[]=Nichts&tags[]=Vegan';
        $few = $this->measure($uri);

        $this->seedRecipes(40);
        $many = $this->measure($uri.'&page=2');

        $this->assertSame($few['count'], $many['count'], implode("\n", $many['sql']));
    }

    public function test_partial_reload_for_paging_saves_tag_and_collection_queries(): void
    {
        $this->actingAsUser();
        $this->seedRecipes(30);
        $version = $this->get('/rezepte')->viewData('page')['version'];

        $full = $this->measure('/rezepte?page=2');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/rezepte?page=2', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
            'X-Inertia-Partial-Component' => 'recipes/Index',
            'X-Inertia-Partial-Data' => 'recipes,pagination,filters',
        ])->assertOk();
        $partial = count(DB::getQueryLog());
        DB::disableQueryLog();

        // ohne Sammlungen (auth.collections), Tag-Liste und Tag-Statistik
        $this->assertSame($full['count'] - 3, $partial, implode("\n", $full['sql']));
    }

    public function test_list_does_not_eager_load_ingredients(): void
    {
        $this->seedRecipes(3);

        $sql = $this->measure('/rezepte')['sql'];

        // Zutaten nur noch in Filter-Unterabfragen (whereHas), nie als eigene Abfrage
        $this->assertSame([], array_values(array_filter(
            $sql,
            fn (string $q) => preg_match('/^select \* from "(recipe_ingredients|ingredients)"/', $q) === 1,
        )));
    }
}
