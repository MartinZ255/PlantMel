<?php

namespace Tests\Feature\Community;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Community\Concerns\CreatesCommunityRecipes;
use Tests\TestCase;

/**
 * E18: Sichtbarkeit von Community-Rezepten je Status und Rolle; Host-
 * Rezeptbuch (/rezepte, Startseite, Home) zeigt keine Community-Rezepte.
 */
class CommunityVisibilityTest extends TestCase
{
    use CreatesCommunityRecipes;
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function visibilityMatrix(): array
    {
        $cases = [];
        $expected = [
            // status => [guest, other user, author, host]
            Recipe::STATUS_DRAFT => [404, 404, 200, 200],
            Recipe::STATUS_PENDING => [404, 404, 200, 200],
            Recipe::STATUS_REJECTED => [404, 404, 200, 200],
            Recipe::STATUS_APPROVED => [200, 200, 200, 200],
        ];
        foreach ($expected as $status => $codes) {
            foreach (['guest', 'other', 'author', 'host'] as $i => $role) {
                $cases["{$status} als {$role}"] = [$status, $role, $codes[$i]];
            }
        }

        return $cases;
    }

    #[DataProvider('visibilityMatrix')]
    public function test_show_respects_status_and_role(string $status, string $role, int $expected): void
    {
        $author = User::factory()->create();
        $recipe = $this->communityRecipe($author, $status);

        match ($role) {
            'other' => $this->actingAsUser(),
            'author' => $this->actingAs($author),
            'host' => $this->actingAsHost(),
            default => null,
        };

        $this->get(route('community.show', $recipe))->assertStatus($expected);
    }

    public function test_status_and_rejection_reason_only_for_author_and_host(): void
    {
        $author = User::factory()->create(['name' => 'Erika']);
        $recipe = $this->communityRecipe($author);
        $recipe->forceFill(['rejection_reason' => 'alt'])->save();

        $this->get(route('community.show', $recipe))
            ->assertInertia(fn (Assert $page) => $page
                ->component('community/Show')
                ->where('recipe.author', 'Erika')
                ->where('recipe.status', null)
                ->where('recipe.rejectionReason', null)
                ->where('can.edit', false)
                ->where('can.report', true));

        $this->actingAs($author)->get(route('community.show', $recipe))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recipe.status', 'approved')
                ->where('recipe.isOwn', true)
                ->where('can.edit', true)
                ->missing('can.rate'));
    }

    public function test_public_list_contains_only_approved_community_recipes(): void
    {
        $author = User::factory()->create(['name' => 'Erika']);
        $approved = $this->communityRecipe($author, Recipe::STATUS_APPROVED, ['title' => 'Freigegeben']);
        $this->communityRecipe($author, Recipe::STATUS_PENDING, ['title' => 'Wartend']);
        $this->communityRecipe($author, Recipe::STATUS_DRAFT, ['title' => 'Entwurf']);
        $this->communityRecipe($author, Recipe::STATUS_REJECTED, ['title' => 'Abgelehnt']);
        Recipe::factory()->create(['title' => 'Host-Rezept']);

        // auch Autor und Host sehen in der öffentlichen Liste nur Freigegebenes
        foreach ([null, $author, User::factory()->host()->create()] as $viewer) {
            $viewer ? $this->actingAs($viewer) : $this->app['auth']->forgetGuards();

            $this->get(route('community.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('community/Index')
                    ->has('recipes', 1)
                    ->where('recipes.0.id', $approved->id)
                    ->where('recipes.0.author', 'Erika')
                    ->where('pagination.total', 1));
        }
    }

    public function test_host_recipe_book_never_shows_community_recipes(): void
    {
        $author = User::factory()->create();
        $community = $this->communityRecipe($author, Recipe::STATUS_APPROVED, ['title' => 'Community-Curry']);
        $host = Recipe::factory()->create(['title' => 'Host-Curry']);
        $vegan = Category::factory()->create(['name' => 'Vegan']);
        $community->categories()->attach($vegan);
        $host->categories()->attach($vegan);

        $this->get(route('recipes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('recipes', 1)
                ->where('recipes.0.id', $host->id)
                ->where('tagStats.0.count', 1));

        $this->get(route('recipes.index', ['search' => 'Curry']))
            ->assertInertia(fn (Assert $page) => $page->has('recipes', 1));

        $this->actingAsUser();
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.totalRecipes', 1)
                ->has('latestRecipes', 1)
                ->where('latestRecipes.0.id', $host->id));
    }

    public function test_landing_never_features_community_recipes(): void
    {
        $community = $this->communityRecipe(User::factory()->create());
        $community->images()->create(['image_path' => 'recipes/c.png']);

        $this->get(route('landing'))
            ->assertInertia(fn (Assert $page) => $page->where('featuredRecipes', []));
    }

    public function test_host_routes_do_not_handle_community_recipes(): void
    {
        $author = User::factory()->create();
        $approved = $this->communityRecipe($author);
        $pending = $this->communityRecipe($author, Recipe::STATUS_PENDING);

        // sichtbare werden auf die Community-Seite umgeleitet, andere: 404
        $this->get(route('recipes.show', $approved))->assertRedirect(route('community.show', $approved));
        $this->get(route('recipes.show', $pending))->assertNotFound();

        $this->actingAsHost();
        $this->get(route('recipes.edit', $approved))->assertNotFound();
        $this->post(route('recipes.update', $approved), ['name' => 'x'])->assertNotFound();
        $this->delete(route('recipes.delete', $approved))->assertNotFound();
        $this->assertDatabaseHas('recipes', ['id' => $approved->id]);
    }

    public function test_foreign_hidden_recipes_cannot_be_collected_and_disappear_from_collections(): void
    {
        $author = User::factory()->create();
        $pending = $this->communityRecipe($author, Recipe::STATUS_PENDING, ['title' => 'Geheim']);
        $approved = $this->communityRecipe($author, Recipe::STATUS_APPROVED, ['title' => 'Öffentlich']);

        $user = $this->actingAsUser();
        $collection = Collection::factory()->for($user)->create();

        $this->post(route('collections.addRecipe', $collection), ['recipe_id' => $pending->id])->assertNotFound();
        $this->post(route('collections.addRecipe', $collection), ['recipe_id' => $approved->id])->assertRedirect();
        $this->assertTrue($collection->recipes()->whereKey($approved->id)->exists());

        // Autor bearbeitet: wieder in Prüfung -> in fremden Sammlungen verborgen
        $approved->forceFill(['status' => Recipe::STATUS_PENDING])->save();

        $this->get(route('collections.show', $collection))
            ->assertInertia(fn (Assert $page) => $page->has('recipes', 0));
        $this->get(route('collections.index'))
            ->assertInertia(fn (Assert $page) => $page->where('collections.0.recipeCount', 0));
    }
}
