<?php

namespace Tests\Feature\Community;

use App\Models\Recipe;
use App\Models\RecipeReport;
use App\Models\RecipeUserRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Community\Concerns\CreatesCommunityRecipes;
use Tests\TestCase;

/**
 * E18: Geschmacks-Bewertung durch andere Nutzer und Meldeweg (Art. 16 DSA).
 */
class CommunityRatingAndReportTest extends TestCase
{
    use CreatesCommunityRecipes;
    use RefreshDatabase;

    public function test_users_rate_once_and_average_is_shown(): void
    {
        $recipe = $this->communityRecipe(User::factory()->create());

        $first = $this->actingAsUser();
        $this->post(route('community.rate', $recipe), ['value' => 5])->assertSessionHas('success');
        $this->post(route('community.rate', $recipe), ['value' => 4]); // ersetzt
        $this->actingAsUser();
        $this->post(route('community.rate', $recipe), ['value' => 2]);

        $this->assertSame(2, RecipeUserRating::count());
        $this->actingAs($first)->get(route('community.show', $recipe))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recipe.rating', 3)
                ->where('recipe.ratingCount', 2)
                ->where('recipe.myRating', 4)
                ->where('can.rate', true));

        $this->post(route('community.rate', $recipe), ['value' => null]);
        $this->assertSame(1, RecipeUserRating::count());
        $this->get(route('community.index'))
            ->assertInertia(fn (Assert $page) => $page->where('recipes.0.rating', 2));
    }

    public function test_author_guest_and_unpublished_cannot_be_rated(): void
    {
        $author = User::factory()->create();
        $recipe = $this->communityRecipe($author);
        $pending = $this->communityRecipe($author, Recipe::STATUS_PENDING);

        $this->post(route('community.rate', $recipe), ['value' => 5])->assertRedirect(route('login'));
        $this->actingAs($author)->post(route('community.rate', $recipe), ['value' => 5])->assertForbidden();
        $this->actingAsUser();
        $this->post(route('community.rate', $pending), ['value' => 5])->assertNotFound();
        $this->post(route('community.rate', $recipe), ['value' => 6])->assertSessionHasErrors('value');
        $this->post(route('community.rate', Recipe::factory()->create()), ['value' => 5])->assertNotFound();

        $this->assertSame(0, RecipeUserRating::count());
    }

    public function test_guest_can_report_published_recipe(): void
    {
        $recipe = $this->communityRecipe(User::factory()->create());

        $this->post(route('community.report', $recipe), [
            'reason' => 'copyright',
            'message' => 'Das Foto stammt von meiner Website.',
            'contact_email' => 'melder@example.test',
            'good_faith' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $report = RecipeReport::firstOrFail();
        $this->assertNull($report->user_id);
        $this->assertSame('copyright', $report->reason);
        $this->assertSame(RecipeReport::STATUS_OPEN, $report->status);
        // keine IP-Adresse gespeichert
        $this->assertArrayNotHasKey('ip', $report->getAttributes());
    }

    public function test_report_validation(): void
    {
        $recipe = $this->communityRecipe(User::factory()->create());

        $this->post(route('community.report', $recipe), ['reason' => 'langweilig', 'good_faith' => '1'])
            ->assertSessionHasErrors('reason');
        $this->post(route('community.report', $recipe), ['reason' => 'illegal', 'good_faith' => '1'])
            ->assertSessionHasErrors('message');
        $this->post(route('community.report', $recipe), ['reason' => 'spam'])
            ->assertSessionHasErrors('good_faith');
        $this->post(route('community.report', $recipe), ['reason' => 'spam', 'good_faith' => '1', 'contact_email' => 'kein-mail'])
            ->assertSessionHasErrors('contact_email');

        $this->assertSame(0, RecipeReport::count());
    }

    public function test_only_published_community_recipes_can_be_reported(): void
    {
        $pending = $this->communityRecipe(User::factory()->create(), Recipe::STATUS_PENDING);
        $host = Recipe::factory()->create();

        $payload = ['reason' => 'spam', 'good_faith' => '1'];
        $this->post(route('community.report', $pending), $payload)->assertNotFound();
        $this->post(route('community.report', $host), $payload)->assertNotFound();
        $this->assertSame(0, RecipeReport::count());
    }

    public function test_reports_are_rate_limited_for_guests(): void
    {
        $recipe = $this->communityRecipe(User::factory()->create());
        $payload = ['reason' => 'spam', 'good_faith' => '1'];

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('community.report', $recipe), $payload)->assertSessionHas('success');
        }

        $this->post(route('community.report', $recipe), $payload)->assertSessionHas('error');
        $this->assertSame(5, RecipeReport::count());
    }
}
