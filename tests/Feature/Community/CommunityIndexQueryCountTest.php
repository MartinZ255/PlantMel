<?php

namespace Tests\Feature\Community;

use App\Models\Category;
use App\Models\RecipeImage;
use App\Models\RecipeUserRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Community\Concerns\CreatesCommunityRecipes;
use Tests\TestCase;

/**
 * E18: Die Community-Liste braucht eine feste Zahl Abfragen, unabhängig
 * von der Zahl der Rezepte (kein N+1 bei Autor, Bild, Kategorien,
 * Bewertung), und blättert mit 24 je Seite.
 */
class CommunityIndexQueryCountTest extends TestCase
{
    use CreatesCommunityRecipes;
    use RefreshDatabase;

    /**
     * count, eine Seite Rezepte (inkl. Ø-Bewertung als Unterabfrage),
     * Kategorien, Autoren, Bilder, Tag-Liste, Tag-Statistik.
     */
    private const EXPECTED_GUEST_QUERIES = 7;

    private function seedRecipes(int $count): void
    {
        $vegan = Category::firstOrCreate(['name' => 'Vegan']);
        $rater = User::factory()->create();

        for ($i = 0; $i < $count; $i++) {
            $recipe = $this->communityRecipe(User::factory()->create());
            $recipe->categories()->attach($vegan);
            RecipeImage::factory()->create(['recipe_id' => $recipe->id]);
            RecipeUserRating::create(['recipe_id' => $recipe->id, 'user_id' => $rater->id, 'value' => 4]);
        }
    }

    private function measure(string $uri): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($uri)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_query_count_is_constant(): void
    {
        $this->seedRecipes(3);
        $few = $this->measure('/community');

        $this->seedRecipes(30);
        $many = $this->measure('/community');
        $second = $this->measure('/community?page=2');

        $this->assertSame(self::EXPECTED_GUEST_QUERIES, $few);
        $this->assertSame($few, $many);
        $this->assertSame($few, $second);
    }

    public function test_paginates_24_per_page_and_redirects_past_last_page(): void
    {
        $this->seedRecipes(25);

        $this->get('/community')->assertInertia(fn (Assert $page) => $page
            ->has('recipes', 24)
            ->where('pagination.lastPage', 2)
            ->where('pagination.total', 25)
            ->where('recipes.0.rating', 4));

        $this->get('/community?page=9')->assertRedirect('/community?page=2');
        $this->get('/community?page[]=x')->assertOk();
    }
}
