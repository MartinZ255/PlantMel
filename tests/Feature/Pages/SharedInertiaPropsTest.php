<?php

namespace Tests\Feature\Pages;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Charakterisierung der geteilten Props aus HandleInertiaRequests::share().
 */
class SharedInertiaPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_user_contains_exactly_id_name_email_and_verification_date(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 14)->setTime(8, 15, 0));
        $user = $this->actingAsUser(['name' => 'Erika', 'email' => 'erika@example.test']);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Slice 4 (F-08): email_verified_at als ISO-8601 (UTC);
                // weiterhin kein is_host, kein avatar
                ->where('auth.user', [
                    'id' => $user->id,
                    'name' => 'Erika',
                    'email' => 'erika@example.test',
                    'email_verified_at' => '2026-11-14T08:15:00Z',
                ])
                ->has('name')
                // Slice 3: `quote` und `sidebarOpen` entfallen (von keinem Vue-Code gelesen).
                ->missing('quote')
                ->missing('sidebarOpen')
            );
    }

    public function test_auth_user_shares_null_verification_date_for_unverified_user(): void
    {
        // Schluessel muss vorhanden sein (null, nicht fehlend): Profile.vue
        // prueft `user.email_verified_at === null`.
        $this->actingAs(User::factory()->unverified()->withoutTwoFactor()->create());

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('auth.user.email_verified_at')
                ->where('auth.user.email_verified_at', null)
            );
    }

    public function test_auth_collections_contains_only_own_collections_newest_first(): void
    {
        $user = $this->actingAsUser();

        $this->travelTo(now()->subDays(3));
        $old = Collection::factory()->for($user)->create(['name' => 'Alt']);
        $this->travelTo(now()->addDay());
        $mid = Collection::factory()->for($user)->create(['name' => 'Mitte']);
        $this->travelBack();
        $new = Collection::factory()->for($user)->create(['name' => 'Neu']);
        Collection::factory()->create(['name' => 'Fremd']);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.collections', [
                    ['id' => $new->id, 'name' => 'Neu'],
                    ['id' => $mid->id, 'name' => 'Mitte'],
                    ['id' => $old->id, 'name' => 'Alt'],
                ])
            );
    }

    public function test_guest_has_null_user_and_empty_collections(): void
    {
        Collection::factory()->create();

        $this->get(route('landing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user', null)
                ->where('auth.collections', [])
            );
    }

    public function test_shared_prop_keys_are_exactly_known(): void
    {
        $this->actingAsUser();

        $props = array_keys($this->get(route('home'))->assertOk()->viewData('page')['props']);

        // Seiteneigene Props von Home (routes/web.php) abziehen; der Rest ist geteilt.
        $shared = array_values(array_diff($props, ['stats', 'latestRecipes']));
        sort($shared);

        $this->assertSame(['auth', 'errors', 'flash', 'name'], $shared);
    }
}
