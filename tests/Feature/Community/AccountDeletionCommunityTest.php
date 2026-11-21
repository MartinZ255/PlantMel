<?php

namespace Tests\Feature\Community;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeReport;
use App\Models\RecipeUserRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Community\Concerns\CreatesCommunityRecipes;
use Tests\TestCase;

/**
 * E18: Konto löschen entfernt eigene Community-Rezepte samt Bildern und
 * ungenutzten Vorschlägen; Host-Rezepte und fremde Rezepte bleiben.
 */
class AccountDeletionCommunityTest extends TestCase
{
    use CreatesCommunityRecipes;
    use RefreshDatabase;

    public function test_profile_announces_community_recipe_count(): void
    {
        $user = $this->actingAsUser();
        $this->communityRecipe($user);
        $this->communityRecipe($user, Recipe::STATUS_DRAFT);

        $this->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('communityRecipeCount', 2));
    }

    public function test_deleting_account_removes_own_community_recipes_and_images(): void
    {
        Storage::fake('public');
        $user = $this->actingAsUser();
        $own = $this->communityRecipe($user);
        Storage::disk('public')->put('recipes/own.png', 'x');
        $own->images()->create(['image_path' => 'recipes/own.png']);
        $proposal = $this->proposal('Nur bei mir', $user);
        $this->useIngredient($own, $proposal);

        $foreign = $this->communityRecipe(User::factory()->create());
        RecipeUserRating::create(['recipe_id' => $foreign->id, 'user_id' => $user->id, 'value' => 4]);
        RecipeReport::create(['recipe_id' => $foreign->id, 'user_id' => $user->id, 'reason' => 'spam']);
        $hostRecipe = Recipe::factory()->create();

        $this->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('landing'));

        $this->assertModelMissing($user);
        $this->assertModelMissing($own);
        $this->assertModelMissing($proposal);
        Storage::disk('public')->assertMissing('recipes/own.png');
        $this->assertModelExists($foreign);
        $this->assertModelExists($hostRecipe);
        $this->assertSame(0, RecipeUserRating::count());
        // Meldung bleibt für die Moderation, ohne Personenbezug
        $this->assertNull(RecipeReport::firstOrFail()->user_id);
        $this->assertSame(0, Ingredient::count());
    }
}
