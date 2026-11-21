<?php

namespace Tests\Feature\Community;

use App\Models\Ingredient;
use App\Models\IngredientAlternative;
use App\Models\Recipe;
use App\Models\RecipeReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Community\Concerns\CreatesCommunityRecipes;
use Tests\TestCase;

/**
 * E18: Moderation (Host): Freigeben, Ablehnen mit Grund, Ausblenden,
 * Vorschläge umbenennen/zuordnen, Meldungen, Badge-Anzahl.
 */
class CommunityModerationTest extends TestCase
{
    use CreatesCommunityRecipes;
    use RefreshDatabase;

    public function test_queue_lists_pending_recipes_with_marked_proposals(): void
    {
        $author = User::factory()->create(['name' => 'Erika']);
        $recipe = $this->communityRecipe($author, Recipe::STATUS_PENDING, ['title' => 'Wartend']);
        $this->useIngredient($recipe, Ingredient::factory()->create(['name' => 'Tofu']), '200 g');
        $this->useIngredient($recipe, $this->proposal('Yuzu-Pfeffer', $author), '1 TL');
        $this->communityRecipe($author, Recipe::STATUS_APPROVED);
        $this->communityRecipe($author, Recipe::STATUS_DRAFT);

        $this->actingAsHost();
        $this->get(route('moderation.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('moderation/Index')
                ->has('pending', 1)
                ->where('pending.0.name', 'Wartend')
                ->where('pending.0.author', 'Erika')
                ->where('pending.0.ingredients', fn ($rows) => collect($rows)->pluck('proposed', 'name')->all() === ['Tofu' => false, 'Yuzu-Pfeffer' => true])
                ->where('catalog', ['Tofu'])
                ->where('moderation.count', 1));
    }

    public function test_approve_publishes_recipe_and_adopts_proposals_into_catalog(): void
    {
        $author = User::factory()->create();
        $recipe = $this->communityRecipe($author, Recipe::STATUS_PENDING);
        $proposal = $this->proposal('Yuzu-Pfeffer', $author);
        $this->useIngredient($recipe, $proposal);

        $host = $this->actingAsHost();
        $this->post(route('moderation.approve', $recipe))->assertRedirect()->assertSessionHas('success');

        $recipe->refresh();
        $this->assertSame(Recipe::STATUS_APPROVED, $recipe->status);
        $this->assertSame($host->id, $recipe->reviewed_by);
        $this->assertNotNull($recipe->reviewed_at);
        $this->assertSame(Ingredient::STATUS_APPROVED, $proposal->fresh()->status);

        // jetzt im Katalog (Zutatensuche) und öffentlich
        $this->get(route('IngredientSearch'))
            ->assertInertia(fn (Assert $page) => $page->where('ingredients.0.name', 'Yuzu-Pfeffer'));
        $this->app['auth']->forgetGuards();
        $this->get(route('community.show', $recipe))->assertOk();
    }

    public function test_reject_requires_reason_which_the_author_sees(): void
    {
        $author = User::factory()->create();
        $recipe = $this->communityRecipe($author, Recipe::STATUS_PENDING);
        $this->actingAsHost();

        $this->post(route('moderation.reject', $recipe), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame(Recipe::STATUS_PENDING, $recipe->fresh()->status);

        $this->post(route('moderation.reject', $recipe), ['reason' => 'Bitte Mengenangaben ergänzen.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(Recipe::STATUS_REJECTED, $recipe->fresh()->status);

        $this->actingAs($author)->get(route('community.show', $recipe))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recipe.status', 'rejected')
                ->where('recipe.rejectionReason', 'Bitte Mengenangaben ergänzen.'));

        $this->actingAsUser();
        $this->get(route('community.show', $recipe))->assertNotFound();
    }

    public function test_rejected_recipe_keeps_its_proposals_out_of_the_catalog(): void
    {
        $author = User::factory()->create();
        $recipe = $this->communityRecipe($author, Recipe::STATUS_PENDING);
        $proposal = $this->proposal('Yuzu-Pfeffer', $author);
        $this->useIngredient($recipe, $proposal);
        $orphan = $this->proposal('Verwaist', $author); // ohne Rezept

        $this->actingAsHost();
        $this->post(route('moderation.reject', $recipe), ['reason' => 'Zu ungenau.']);

        $this->assertSame(Ingredient::STATUS_PROPOSED, $proposal->fresh()->status);
        $this->assertModelMissing($orphan);
        $this->get(route('IngredientSearch'))
            ->assertInertia(fn (Assert $page) => $page->has('ingredients', 0));
    }

    public function test_hide_puts_published_recipe_back_into_review(): void
    {
        $recipe = $this->communityRecipe(User::factory()->create());
        RecipeReport::create(['recipe_id' => $recipe->id, 'reason' => 'copyright', 'message' => 'Fremdes Foto']);

        $this->actingAsHost();
        $this->get(route('moderation.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('reports', 1)
                ->where('reports.0.reason', 'Urheberrecht oder Bildrechte verletzt')
                ->where('moderation.count', 1));

        $this->post(route('moderation.hide', $recipe))->assertSessionHas('success');
        $this->assertSame(Recipe::STATUS_PENDING, $recipe->fresh()->status);

        $report = RecipeReport::firstOrFail();
        $this->post(route('moderation.reports.resolve', $report))->assertSessionHas('success');
        $this->assertSame(RecipeReport::STATUS_RESOLVED, $report->fresh()->status);

        $this->app['auth']->forgetGuards();
        $this->get(route('community.show', $recipe))->assertNotFound();
    }

    public function test_rename_proposal_and_refuse_existing_name(): void
    {
        $author = User::factory()->create();
        $recipe = $this->communityRecipe($author, Recipe::STATUS_PENDING);
        $proposal = $this->proposal('yuzu pfefer', $author);
        $this->useIngredient($recipe, $proposal);
        Ingredient::factory()->create(['name' => 'Tofu']);

        $this->actingAsHost();
        $this->patch(route('moderation.ingredients.rename', $proposal), ['name' => 'Tofu'])
            ->assertSessionHasErrors('name');
        $this->patch(route('moderation.ingredients.rename', $proposal), ['name' => 'Yuzu-Pfeffer'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Yuzu-Pfeffer', $proposal->fresh()->name);

        $catalog = Ingredient::where('name', 'Tofu')->first();
        $this->patch(route('moderation.ingredients.rename', $catalog), ['name' => 'Seidentofu'])->assertNotFound();
    }

    public function test_merge_proposal_into_catalog_ingredient(): void
    {
        $author = User::factory()->create();
        $recipe = $this->communityRecipe($author, Recipe::STATUS_PENDING);
        $proposal = $this->proposal('Kichererbse', $author);
        $target = Ingredient::factory()->create(['name' => 'Kichererbsen (gekocht)']);
        $alt = Ingredient::factory()->create(['name' => 'Bohnen']);
        $this->useIngredient($recipe, $proposal, '1 Dose');
        IngredientAlternative::create(['recipe_id' => $recipe->id, 'ingredient_id' => $proposal->id, 'alternative_ingredient_id' => $alt->id]);

        $this->actingAsHost();
        $this->post(route('moderation.ingredients.merge', $proposal), ['target' => 'Gibt es nicht'])
            ->assertSessionHasErrors('target');
        $this->post(route('moderation.ingredients.merge', $proposal), ['target' => 'Kichererbsen (gekocht)'])
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($proposal);
        $this->assertDatabaseHas('recipe_ingredients', ['recipe_id' => $recipe->id, 'ingredient_id' => $target->id, 'note' => '1 Dose']);
        $this->assertDatabaseHas('ingredient_alternatives', ['recipe_id' => $recipe->id, 'ingredient_id' => $target->id, 'alternative_ingredient_id' => $alt->id]);
    }

    public function test_non_hosts_cannot_moderate(): void
    {
        $author = User::factory()->create();
        $recipe = $this->communityRecipe($author, Recipe::STATUS_PENDING);

        $this->actingAs($author);
        $this->post(route('moderation.approve', $recipe))->assertForbidden();
        $this->get(route('moderation.index'))->assertForbidden();
        $this->assertSame(Recipe::STATUS_PENDING, $recipe->fresh()->status);
    }

    public function test_moderation_actions_do_not_apply_to_host_recipes(): void
    {
        $recipe = Recipe::factory()->create();
        $this->actingAsHost();

        $this->post(route('moderation.reject', $recipe), ['reason' => 'Test test'])->assertForbidden();
        $this->assertSame(Recipe::STATUS_APPROVED, $recipe->fresh()->status);
    }

    public function test_badge_count_is_shared_only_with_hosts(): void
    {
        $author = User::factory()->create();
        $this->communityRecipe($author, Recipe::STATUS_PENDING);
        $this->communityRecipe($author, Recipe::STATUS_PENDING);
        $reported = $this->communityRecipe($author);
        RecipeReport::create(['recipe_id' => $reported->id, 'reason' => 'spam']);
        RecipeReport::create(['recipe_id' => $reported->id, 'reason' => 'other', 'message' => 'x']);

        $this->actingAsUser();
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('moderation', null));

        $this->actingAsHost();
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('moderation.count', 3));
    }
}
