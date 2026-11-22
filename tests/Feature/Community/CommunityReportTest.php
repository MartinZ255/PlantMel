<?php

namespace Tests\Feature\Community;

use App\Models\Recipe;
use App\Models\RecipeReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Community\Concerns\CreatesCommunityRecipes;
use Tests\TestCase;

/**
 * E18: Meldeweg (Art. 16 DSA) – auch für Gäste, mit Rate-Limit, ohne IP.
 */
class CommunityReportTest extends TestCase
{
    use CreatesCommunityRecipes;
    use RefreshDatabase;

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
