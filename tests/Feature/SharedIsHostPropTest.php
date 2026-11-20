<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Gate C2: `auth.user.is_host` wird global geteilt (HandleInertiaRequests),
 * damit die Kopfnavigation „Rezept erstellen“ auf jeder Seite nur für Hosts
 * zeigt – auch auf Seiten ohne eigene Prop `isHost`.
 *
 * Host: true · normaler Nutzer: false (echter bool) · Gast: auth.user = null.
 */
class SharedIsHostPropTest extends TestCase
{
    use RefreshDatabase;

    /** Seiten ohne eigene Host-Prop (die Rezeptseiten liefern zusätzlich `isHost`). */
    private const AUTH_ROUTES = ['home', 'settings.index', 'collections.index', 'legal.imprint'];

    public function test_host_gets_is_host_true_on_every_page(): void
    {
        $host = User::factory()->host()->create();

        foreach (self::AUTH_ROUTES as $name) {
            $this->actingAs($host)
                ->get(route($name))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('auth.user.id', $host->id)
                    ->where('auth.user.is_host', fn ($value) => $value === true)
                );
        }
    }

    public function test_regular_user_gets_is_host_false_as_boolean(): void
    {
        $user = User::factory()->create();

        foreach (self::AUTH_ROUTES as $name) {
            $this->actingAs($user)
                ->get(route($name))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    // strikter Vergleich: false, nicht null/0
                    ->where('auth.user.is_host', fn ($value) => $value === false)
                );
        }
    }

    public function test_guest_has_no_user_and_therefore_no_is_host(): void
    {
        foreach (['landing', 'recipes.index', 'legal.imprint', 'login'] as $name) {
            $this->get(route($name))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('auth.user', null)
                );
        }
    }

    public function test_is_host_is_not_mass_assignable_via_shared_props(): void
    {
        // Rückfrage F-46: das Teilen ändert nichts an der Rechtevergabe
        $user = User::factory()->create();
        $user->fill(['is_host' => true])->save();

        $this->actingAs($user->fresh())
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.is_host', false)
            );
    }
}
