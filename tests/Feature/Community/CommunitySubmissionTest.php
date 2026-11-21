<?php

namespace Tests\Feature\Community;

use App\Http\Controllers\CommunityRecipeController;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Community\Concerns\CreatesCommunityRecipes;
use Tests\TestCase;

/**
 * E18: Einreichen, Bearbeiten und Löschen eigener Community-Rezepte;
 * Autorisierung fremder Rezepte; Statuswechsel.
 */
class CommunitySubmissionTest extends TestCase
{
    use CreatesCommunityRecipes;
    use RefreshDatabase;

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'bild.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')
        );
    }

    public function test_non_host_submits_recipe_as_pending_community_recipe(): void
    {
        $user = $this->actingAsUser();
        Category::factory()->create(['name' => 'Vegan']);

        $response = $this->post(route('community.store'), $this->communityPayload([
            'tags' => ['Vegan', 'Gibt es nicht'],
        ]));

        $recipe = Recipe::latest('id')->firstOrFail();
        $response->assertRedirect(route('community.show', $recipe))
            ->assertSessionHas('success', CommunityRecipeController::MESSAGE_SUBMITTED);

        $this->assertSame(Recipe::TYPE_COMMUNITY, $recipe->type);
        $this->assertSame(Recipe::STATUS_PENDING, $recipe->status);
        $this->assertSame($user->id, $recipe->user_id);
        $this->assertNotNull($recipe->submitted_at);
        // nur vorhandene Kategorien, keine neue angelegt
        $this->assertSame(['Vegan'], $recipe->categories->pluck('name')->all());
        $this->assertSame(1, Category::count());
    }

    public function test_draft_intent_saves_draft(): void
    {
        $this->actingAsUser();

        $this->post(route('community.store'), $this->communityPayload(['intent' => 'draft']))
            ->assertSessionHas('success', CommunityRecipeController::MESSAGE_DRAFT);

        $recipe = Recipe::latest('id')->firstOrFail();
        $this->assertSame(Recipe::STATUS_DRAFT, $recipe->status);
        $this->assertNull($recipe->submitted_at);
    }

    public function test_status_and_type_cannot_be_injected(): void
    {
        $user = $this->actingAsUser();

        $this->post(route('community.store'), $this->communityPayload([
            'status' => 'approved', 'type' => 'host', 'user_id' => 999, 'intent' => 'submit',
        ]))->assertRedirect();

        $recipe = Recipe::latest('id')->firstOrFail();
        $this->assertSame(Recipe::STATUS_PENDING, $recipe->status);
        $this->assertSame(Recipe::TYPE_COMMUNITY, $recipe->type);
        $this->assertSame($user->id, $recipe->user_id);
    }

    public function test_validation_like_host_form(): void
    {
        $this->actingAsUser();

        $this->post(route('community.store'), ['name' => ''])
            ->assertSessionHasErrors(['name', 'ingredients', 'steps']);

        $this->post(route('community.store'), $this->communityPayload([
            'image' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
        ]))->assertSessionHasErrors('image');

        $this->post(route('community.store'), $this->communityPayload(['intent' => 'publish']))
            ->assertSessionHasErrors('intent');

        $this->assertSame(0, Recipe::count());
    }

    public function test_alternatives_must_be_catalog_ingredients(): void
    {
        $this->actingAsUser();
        $proposal = $this->proposal('Geheimzutat');
        $catalog = Ingredient::factory()->create(['name' => 'Tofu']);

        $this->post(route('community.store'), $this->communityPayload([
            'ingredients' => [['amount' => '1', 'name' => 'Kichererbsen', 'alternativeIngredientIds' => [$proposal->id]]],
        ]))->assertSessionHasErrors('ingredients.0.alternativeIngredientIds.0');

        $this->post(route('community.store'), $this->communityPayload([
            'ingredients' => [['amount' => '1', 'name' => 'Kichererbsen', 'alternativeIngredientIds' => [$catalog->id]]],
        ]))->assertSessionHasNoErrors();
    }

    public function test_image_upload_is_stored_on_public_disk(): void
    {
        Storage::fake('public');
        $this->actingAsUser();

        $this->post(route('community.store'), $this->communityPayload(['image' => $this->png()]))
            ->assertSessionHasNoErrors();

        $image = RecipeImage::firstOrFail();
        Storage::disk('public')->assertExists($image->image_path);
    }

    public function test_store_is_rate_limited_per_user(): void
    {
        $this->actingAsUser();

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('community.store'), $this->communityPayload(['name' => "Rezept {$i}"]))
                ->assertSessionHasNoErrors();
        }

        $this->from(route('community.create'))
            ->post(route('community.store'), $this->communityPayload(['name' => 'Elftes']))
            ->assertRedirect(route('community.create'))
            ->assertSessionHas('error');

        $this->assertSame(10, Recipe::count());
    }

    public function test_author_edit_of_approved_recipe_goes_back_to_pending(): void
    {
        $author = User::factory()->create();
        $recipe = $this->communityRecipe($author);
        $this->actingAs($author);

        $this->get(route('community.edit', $recipe))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('community/Edit')
                ->where('recipe.status', 'approved')
                ->where('requiresReview', true));

        $this->post(route('community.update', $recipe), $this->communityPayload(['name' => 'Neu']))
            ->assertRedirect(route('community.show', $recipe))
            ->assertSessionHas('success', CommunityRecipeController::MESSAGE_RESUBMITTED);

        $recipe->refresh();
        $this->assertSame('Neu', $recipe->title);
        $this->assertSame(Recipe::STATUS_PENDING, $recipe->status);

        // nicht mehr öffentlich
        $this->app['auth']->forgetGuards();
        $this->get(route('community.show', $recipe))->assertNotFound();
    }

    public function test_resubmitting_rejected_recipe_clears_reason(): void
    {
        $author = User::factory()->create();
        $recipe = $this->communityRecipe($author, Recipe::STATUS_REJECTED);
        $recipe->forceFill(['rejection_reason' => 'Mengen fehlen'])->save();

        $this->actingAs($author)->post(route('community.update', $recipe), $this->communityPayload());

        $recipe->refresh();
        $this->assertSame(Recipe::STATUS_PENDING, $recipe->status);
        $this->assertNull($recipe->rejection_reason);
    }

    public function test_host_edit_keeps_status(): void
    {
        $recipe = $this->communityRecipe(User::factory()->create());
        $this->actingAsHost();

        $this->post(route('community.update', $recipe), $this->communityPayload(['name' => 'Vom Host korrigiert']))
            ->assertSessionHas('success', 'Rezept wurde gespeichert.');

        $recipe->refresh();
        $this->assertSame(Recipe::STATUS_APPROVED, $recipe->status);
        $this->assertSame('Vom Host korrigiert', $recipe->title);
    }

    public function test_foreign_recipes_cannot_be_edited_or_deleted(): void
    {
        $author = User::factory()->create();
        $approved = $this->communityRecipe($author);
        $pending = $this->communityRecipe($author, Recipe::STATUS_PENDING);
        $this->actingAsUser();

        // sichtbar, aber fremd: 403; nicht sichtbar: 404 (verrät nichts)
        $this->get(route('community.edit', $approved))->assertForbidden();
        $this->post(route('community.update', $approved), $this->communityPayload(['name' => 'Gekapert']))->assertForbidden();
        $this->delete(route('community.destroy', $approved))->assertForbidden();
        $this->get(route('community.edit', $pending))->assertNotFound();
        $this->delete(route('community.destroy', $pending))->assertNotFound();

        $this->assertSame($approved->title, $approved->fresh()->title);
        $this->assertDatabaseHas('recipes', ['id' => $pending->id]);
    }

    public function test_community_routes_reject_host_recipes(): void
    {
        $hostRecipe = Recipe::factory()->create();
        $this->actingAsHost();

        $this->get(route('community.show', $hostRecipe))->assertNotFound();
        $this->get(route('community.edit', $hostRecipe))->assertNotFound();
        $this->delete(route('community.destroy', $hostRecipe))->assertNotFound();
    }

    public function test_author_and_host_can_delete_with_images(): void
    {
        Storage::fake('public');
        $author = User::factory()->create();
        $recipe = $this->communityRecipe($author, Recipe::STATUS_PENDING);
        Storage::disk('public')->put('recipes/a.png', 'x');
        $recipe->images()->create(['image_path' => 'recipes/a.png']);

        $this->actingAs($author)->delete(route('community.destroy', $recipe))
            ->assertRedirect(route('community.mine'));
        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
        Storage::disk('public')->assertMissing('recipes/a.png');

        $other = $this->communityRecipe(User::factory()->create());
        $this->actingAsHost();
        $this->delete(route('community.destroy', $other))
            ->assertRedirect(route('moderation.index'));
        $this->assertDatabaseMissing('recipes', ['id' => $other->id]);
    }

    public function test_mine_lists_only_own_recipes_with_status(): void
    {
        $author = User::factory()->create();
        $rejected = $this->communityRecipe($author, Recipe::STATUS_REJECTED);
        $rejected->forceFill(['rejection_reason' => 'Bitte Mengen ergänzen'])->save();
        $this->communityRecipe($author, Recipe::STATUS_DRAFT);
        $this->communityRecipe(User::factory()->create());
        Recipe::factory()->create();

        $this->actingAs($author)->get(route('community.mine'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('community/Mine')
                ->has('recipes', 2)
                ->where('recipes', fn ($recipes) => collect($recipes)->firstWhere('id', $rejected->id)['rejectionReason'] === 'Bitte Mengen ergänzen'));
    }

    public function test_create_offers_only_catalog_ingredients_and_import_flag(): void
    {
        $user = $this->actingAsUser();
        Ingredient::factory()->create(['name' => 'Tofu']);
        $this->proposal('Fremder Vorschlag');

        $this->get(route('community.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('community/Create')
                ->has('ingredients', 1)
                ->where('ingredients.0.name', 'Tofu')
                ->where('importAvailable', false));

        $user->forceFill(['gemini_api_key' => $this->geminiKey()])->save();
        $this->get(route('community.create'))
            ->assertInertia(fn (Assert $page) => $page->where('importAvailable', true));
    }
}
