<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * F-08 (Slice 4, Entscheidung E2): E-Mail-Verifizierung bei ABGESCHALTETEM
 * Schalter `plantmel.require_email_verification` (Default). Die App verhaelt
 * sich wie vor Slice 4: kein Versand, keine Sperre, kein Profilhinweis.
 *
 * Ersetzt die Ist-Tests test_f08_unverified_user_reaches_home und
 * test_f45_unverified_host_reaches_host_routes aus AuthorizationGapsTest.
 */
#[Group('F-08')]
class EmailVerificationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_switch_is_off_by_default(): void
    {
        $this->assertFalse(config('plantmel.require_email_verification'));
        $this->assertFalse(User::emailVerificationRequired());
    }

    public function test_registration_sends_nothing(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Neue Person',
            'email' => 'neu@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home', absolute: false));

        Notification::assertNothingSent();

        // Spalte bleibt unveraendert null; ausgewertet wird sie erst bei AN.
        $this->assertNull(User::where('email', 'neu@example.com')->sole()->email_verified_at);

        $this->get(route('home'))->assertOk();
    }

    public function test_unverified_user_reaches_home_and_user_routes(): void
    {
        $user = User::factory()->unverified()->withoutTwoFactor()->create();

        $this->actingAs($user)->get(route('home'))->assertOk();

        $this->actingAs($user)
            ->post(route('collections.store'), ['name' => 'Ohne Verifizierung'])
            ->assertRedirect();

        $this->assertDatabaseHas('collections', ['user_id' => $user->id, 'name' => 'Ohne Verifizierung']);
    }

    public function test_unverified_host_reaches_host_routes(): void
    {
        $host = User::factory()->host()->unverified()->withoutTwoFactor()->create();

        $this->actingAs($host)->get(route('recipes.create'))->assertOk();
    }

    public function test_verification_notice_redirects_to_home(): void
    {
        $user = User::factory()->unverified()->withoutTwoFactor()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertRedirect(route('home', absolute: false));
    }

    public function test_verification_send_sends_nothing(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->withoutTwoFactor()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('home', absolute: false));

        Notification::assertNothingSent();
    }

    public function test_verification_link_is_harmless(): void
    {
        $user = User::factory()->unverified()->withoutTwoFactor()->create();
        Event::fake([Verified::class]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect(route('home', absolute: false).'?verified=1');

        Event::assertNotDispatched(Verified::class);
    }

    public function test_profile_page_shows_no_verification_hint(): void
    {
        $user = User::factory()->unverified()->withoutTwoFactor()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Profile')
                ->where('mustVerifyEmail', false)
            );
    }

    public function test_profile_email_change_sends_nothing(): void
    {
        Notification::fake();
        $user = $this->actingAsUser();

        $this->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'neu@example.com',
        ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Profil gespeichert.');

        Notification::assertNothingSent();
        // Wie vor Slice 4: die Spalte wird bei Adresswechsel zurueckgesetzt.
        $this->assertNull($user->fresh()->email_verified_at);
        $this->get(route('home'))->assertOk();
    }
}
