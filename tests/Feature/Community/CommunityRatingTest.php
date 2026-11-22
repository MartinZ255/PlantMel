<?php

namespace Tests\Feature\Community;

use App\Models\RatingDimension;
use App\Models\Recipe;
use App\Models\RecipeRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Community\Concerns\CreatesCommunityRecipes;
use Tests\TestCase;

/**
 * E18 / E14: Community-Rezepte werden wie Host-Rezepte bewertet – der Autor
 * vergibt im Formular optional 1–5 Sterne „Geschmack“ (recipe_ratings über
 * die Geschmacks-Dimension). Andere Nutzer bewerten nicht; der Host
 * überschreibt die Bewertung des Autors beim Bearbeiten nicht.
 */
class CommunityRatingTest extends TestCase
{
    use CreatesCommunityRecipes;
    use RefreshDatabase;

    private RatingDimension $taste;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taste = RatingDimension::factory()->create(['name' => 'Geschmack']);
    }

    private function tasteOf(Recipe $recipe): ?int
    {
        $value = RecipeRating::where('recipe_id', $recipe->id)->where('dimension_id', $this->taste->id)->value('value');

        return $value === null ? null : (int) $value;
    }

    public function test_author_sets_changes_and_removes_rating(): void
    {
        $author = $this->actingAsUser();

        $this->post(route('community.store'), $this->communityPayload([
            'ratings' => [$this->taste->id => 4],
        ]))->assertSessionHasNoErrors();
        $recipe = Recipe::latest('id')->firstOrFail();
        $this->assertSame(4, $this->tasteOf($recipe));

        $this->get(route('community.edit', $recipe))
            ->assertInertia(fn (Assert $page) => $page
                ->has('ratingDimensions', 1)
                ->where('recipe.ratings', [$this->taste->id => 4]));

        $this->post(route('community.update', $recipe), $this->communityPayload([
            'ratings' => [$this->taste->id => 2],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(2, $this->tasteOf($recipe));

        // „Bewertung entfernen“ sendet null
        $this->post(route('community.update', $recipe), $this->communityPayload([
            'ratings' => [$this->taste->id => null],
        ]))->assertSessionHasNoErrors();
        $this->assertNull($this->tasteOf($recipe));
        $this->assertSame($author->id, $recipe->fresh()->user_id);
    }

    public function test_rating_is_optional_and_validated_like_host_form(): void
    {
        $this->actingAsUser();
        $other = RatingDimension::factory()->create(['name' => 'Aufwand']);

        $this->post(route('community.store'), $this->communityPayload())->assertSessionHasNoErrors();
        $this->assertSame(0, RecipeRating::count());

        $this->post(route('community.store'), $this->communityPayload(['ratings' => [$this->taste->id => 6]]))
            ->assertSessionHasErrors('ratings.'.$this->taste->id);
        $this->post(route('community.store'), $this->communityPayload(['ratings' => [$other->id => 3]]))
            ->assertSessionHasErrors('ratings');
        $this->assertSame(0, RecipeRating::count());
    }

    public function test_list_and_detail_show_author_rating(): void
    {
        $recipe = $this->communityRecipe(User::factory()->create());
        RecipeRating::create(['recipe_id' => $recipe->id, 'dimension_id' => $this->taste->id, 'value' => 5]);

        $this->get(route('community.index'))
            ->assertInertia(fn (Assert $page) => $page->where('recipes.0.rating', 5));
        $this->get(route('community.show', $recipe))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recipe.rating', 5)
                ->where('recipe.ratingCount', 1)
                ->missing('recipe.myRating'));
    }

    public function test_other_users_cannot_rate(): void
    {
        $recipe = $this->communityRecipe(User::factory()->create());
        $this->assertFalse(Schema::hasTable('recipe_user_ratings'));
        $this->assertFalse(Route::has('community.rate'));

        // fremdes Rezept bearbeiten (und damit bewerten) ist verboten
        $this->actingAsUser();
        $this->post(route('community.update', $recipe), $this->communityPayload([
            'ratings' => [$this->taste->id => 1],
        ]))->assertForbidden();
        $this->assertNull($this->tasteOf($recipe));
    }

    public function test_host_edit_does_not_overwrite_author_rating(): void
    {
        $recipe = $this->communityRecipe(User::factory()->create());
        RecipeRating::create(['recipe_id' => $recipe->id, 'dimension_id' => $this->taste->id, 'value' => 4]);
        $this->actingAsHost();

        // ohne Bewertung (Abschnitt im Formular ausgeblendet) …
        $this->post(route('community.update', $recipe), $this->communityPayload(['name' => 'Korrektur']))
            ->assertSessionHasNoErrors();
        $this->assertSame(4, $this->tasteOf($recipe));

        // … und auch mit abweichender oder entfernter Bewertung
        $this->post(route('community.update', $recipe), $this->communityPayload(['ratings' => [$this->taste->id => 1]]));
        $this->post(route('community.update', $recipe), $this->communityPayload(['ratings' => [$this->taste->id => null]]));
        $this->assertSame(4, $this->tasteOf($recipe));
        $this->assertSame('Ofen-Kichererbsen', $recipe->fresh()->title);
    }

    public function test_host_editing_own_community_recipe_rates_as_author(): void
    {
        $host = $this->actingAsHost();
        $recipe = $this->communityRecipe($host, Recipe::STATUS_PENDING);

        $this->post(route('community.update', $recipe), $this->communityPayload(['ratings' => [$this->taste->id => 3]]));

        $this->assertSame(3, $this->tasteOf($recipe));
    }
}
