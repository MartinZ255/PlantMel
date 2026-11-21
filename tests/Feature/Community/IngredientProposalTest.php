<?php

namespace Tests\Feature\Community;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Community\Concerns\CreatesCommunityRecipes;
use Tests\TestCase;

/**
 * E18: Zutaten-Vorschläge – entstehen nur aus Community-Rezepten, bleiben
 * bis zur Freigabe außerhalb des Katalogs und werden ungenutzt verworfen.
 */
class IngredientProposalTest extends TestCase
{
    use CreatesCommunityRecipes;
    use RefreshDatabase;

    public function test_unknown_ingredient_becomes_proposal_known_one_is_reused(): void
    {
        $user = $this->actingAsUser();
        $tofu = Ingredient::factory()->create(['name' => 'Tofu']);

        $this->post(route('community.store'), $this->communityPayload([
            'ingredients' => [
                ['amount' => '200 g', 'name' => 'tofu'],            // Katalog, andere Schreibweise
                ['amount' => '1 TL', 'name' => 'Yuzu-Pfeffer'],     // neu
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, Ingredient::count());
        $proposal = Ingredient::where('name', 'Yuzu-Pfeffer')->firstOrFail();
        $this->assertSame(Ingredient::STATUS_PROPOSED, $proposal->status);
        $this->assertSame($user->id, $proposal->proposed_by);
        $this->assertDatabaseHas('recipe_ingredients', ['ingredient_id' => $tofu->id]);
    }

    public function test_proposals_are_invisible_in_catalog_views(): void
    {
        Ingredient::factory()->create(['name' => 'Tofu']);
        $this->proposal('Yuzu-Pfeffer');

        $this->get(route('IngredientSearch'))
            ->assertInertia(fn (Assert $page) => $page->has('ingredients', 1)->where('ingredients.0.name', 'Tofu'));

        $this->actingAsHost();
        $this->get(route('recipes.create'))
            ->assertInertia(fn (Assert $page) => $page->has('ingredients', 1));
        $this->get(route('recipes.edit', Recipe::factory()->create()))
            ->assertInertia(fn (Assert $page) => $page->has('ingredients', 1));
    }

    public function test_second_user_reuses_open_proposal_without_seeing_it(): void
    {
        $first = User::factory()->create();
        $recipe = $this->communityRecipe($first, Recipe::STATUS_PENDING);
        $proposal = $this->proposal('Yuzu-Pfeffer', $first);
        $this->useIngredient($recipe, $proposal);

        $this->actingAsUser();
        $this->get(route('community.create'))->assertInertia(fn (Assert $page) => $page->has('ingredients', 0));
        $this->post(route('community.store'), $this->communityPayload([
            'ingredients' => [['amount' => '', 'name' => 'Yuzu-Pfeffer']],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Ingredient::count());
        $this->assertSame(2, $proposal->recipeIngredients()->count());
    }

    public function test_deleting_recipe_discards_unused_proposals_only(): void
    {
        $author = User::factory()->create();
        $a = $this->communityRecipe($author, Recipe::STATUS_PENDING);
        $b = $this->communityRecipe($author, Recipe::STATUS_PENDING);
        $only = $this->proposal('Nur in A', $author);
        $shared = $this->proposal('In A und B', $author);
        $catalog = Ingredient::factory()->create(['name' => 'Tofu']);
        $this->useIngredient($a, $only);
        $this->useIngredient($a, $shared);
        $this->useIngredient($b, $shared);
        $this->useIngredient($a, $catalog);

        $this->actingAs($author)->delete(route('community.destroy', $a))->assertRedirect();

        $this->assertModelMissing($only);
        $this->assertModelExists($shared);
        $this->assertModelExists($catalog); // Katalog bleibt immer
    }

    public function test_removing_proposal_in_edit_discards_it(): void
    {
        $author = User::factory()->create();
        $recipe = $this->communityRecipe($author, Recipe::STATUS_PENDING);
        $proposal = $this->proposal('Tippfehler', $author);
        $this->useIngredient($recipe, $proposal);

        $this->actingAs($author)->post(route('community.update', $recipe), $this->communityPayload([
            'ingredients' => [['amount' => '1', 'name' => 'Richtig']],
        ]))->assertSessionHasNoErrors();

        $this->assertModelMissing($proposal);
        $this->assertSame(Ingredient::STATUS_PROPOSED, Ingredient::where('name', 'Richtig')->value('status'));
    }

    public function test_host_actions_adopt_proposals_into_catalog(): void
    {
        $this->proposal('Yuzu-Pfeffer');
        $this->proposal('Shiso');
        $this->actingAsHost();

        // Host legt die gleiche Zutat an: kein 422, Vorschlag wird übernommen
        $this->postJson(route('ingredients.store'), ['name' => 'Yuzu-Pfeffer'])->assertCreated();
        $this->assertSame(Ingredient::STATUS_APPROVED, Ingredient::where('name', 'Yuzu-Pfeffer')->value('status'));

        // Host-Formular nutzt einen Vorschlag: ebenfalls übernommen
        $this->post(route('recipes.store'), [
            'name' => 'Host-Rezept',
            'ingredients' => [['amount' => '', 'name' => 'Shiso']],
            'steps' => ['Kochen.'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(Ingredient::STATUS_APPROVED, Ingredient::where('name', 'Shiso')->value('status'));
        $this->assertSame(2, Ingredient::count());
    }

    public function test_proposed_flags_visible_to_author_and_host_only(): void
    {
        $author = User::factory()->create();
        $recipe = $this->communityRecipe($author);
        $this->useIngredient($recipe, $this->proposal('Yuzu-Pfeffer', $author));

        $this->get(route('community.show', $recipe))
            ->assertInertia(fn (Assert $page) => $page->where('recipe.ingredients.0.proposed', false));
        $this->actingAs($author)->get(route('community.show', $recipe))
            ->assertInertia(fn (Assert $page) => $page->where('recipe.ingredients.0.proposed', true));
    }
}
