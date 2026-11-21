<?php

namespace Tests\Feature\Pages;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
                // kein avatar. Gate C2: is_host (bool) wird jetzt geteilt,
                // siehe tests/Feature/SharedIsHostPropTest.php
                ->where('auth.user', [
                    'id' => $user->id,
                    'name' => 'Erika',
                    'email' => 'erika@example.test',
                    'email_verified_at' => '2026-11-14T08:15:00Z',
                    'is_host' => false,
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

    public function test_auth_collections_are_shared_on_other_pages_for_the_header_menu(): void
    {
        // Gate D2: Das Kopfmenue "Sammlungen" (useAppNavigation) braucht die
        // Liste auf jeder Seite mit Kopfzeile, nicht nur auf Home.
        $user = $this->actingAsUser();
        $collection = Collection::factory()->for($user)->create(['name' => 'Wochenplan']);

        foreach ([route('recipes.index'), route('collections.index'), route('IngredientSearch')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('auth.collections', [['id' => $collection->id, 'name' => 'Wochenplan']])
                );
        }
    }

    public function test_auth_collections_are_lazy_and_skipped_on_partial_reloads(): void
    {
        // Gate D2 (F-23): Partial Reloads ohne `auth` fragen keine Sammlungen ab.
        $user = $this->actingAsUser();
        Collection::factory()->for($user)->create();
        $version = $this->get(route('recipes.index'))->viewData('page')['version'];

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->get(route('recipes.index'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
            'X-Inertia-Partial-Component' => 'recipes/Index',
            'X-Inertia-Partial-Data' => 'recipes,pagination,filters',
        ]);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertArrayNotHasKey('auth', $response->json('props'));
        $this->assertSame([], array_values(array_filter(
            $queries,
            fn (string $q) => str_contains($q, 'from "collections"'),
        )));
    }

    public function test_shared_prop_keys_are_exactly_known(): void
    {
        $this->actingAsUser();

        $props = array_keys($this->get(route('home'))->assertOk()->viewData('page')['props']);

        // Seiteneigene Props von Home (routes/web.php) abziehen; der Rest ist geteilt.
        $shared = array_values(array_diff($props, ['stats', 'latestRecipes']));
        sort($shared);

        // E17: `features` (Schalter für den Google-Knopf) neu.
        // E18: `moderation` (Badge „Zu prüfen“, für Nicht-Hosts null) neu.
        $this->assertSame(['auth', 'errors', 'features', 'flash', 'moderation', 'name'], $shared);
    }

    public function test_features_google_login_is_false_without_configuration(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('features', ['googleLogin' => false])
            );
    }

    public function test_features_google_login_needs_client_id_and_secret(): void
    {
        config(['services.google.client_id' => 'test-client-id', 'services.google.client_secret' => null]);
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('features.googleLogin', false));

        config(['services.google.client_id' => 'test-client-id', 'services.google.client_secret' => 'test-secret']);
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('features.googleLogin', true));
        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page->where('features.googleLogin', true));
    }
}
