<?php

namespace Tests\Feature\Auth;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * F-08 (Slice 4, Entscheidung E2): E-Mail-Verifizierung bei EINGESCHALTETEM
 * Schalter `plantmel.require_email_verification`.
 *
 * Die ersten drei Tests stammen aus tests/KnownDefects/Security (vorher rot).
 * Das Verhalten bei abgeschaltetem Schalter (Default) haelt
 * EmailVerificationDisabledTest fest.
 */
#[Group('F-08')]
class EmailVerificationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['plantmel.require_email_verification' => true]);
    }

    public function test_unverified_user_is_redirected_to_verification_notice_from_home(): void
    {
        $user = User::factory()->unverified()->withoutTwoFactor()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_user_cannot_create_collection(): void
    {
        $user = User::factory()->unverified()->withoutTwoFactor()->create();

        $this->actingAs($user)
            ->post(route('collections.store'), ['name' => 'Unverifiziert'])
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('collections', 0);
    }

    public function test_registration_sends_email_verification_notification(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Neue Person',
            'email' => 'neu@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'neu@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertCount(1);
    }

    public function test_unverified_host_is_redirected_from_host_routes(): void
    {
        $host = User::factory()->host()->unverified()->withoutTwoFactor()->create();
        $recipe = Recipe::factory()->create();

        $this->actingAs($host)
            ->get(route('recipes.create'))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($host)
            ->delete(route('recipes.delete', $recipe))
            ->assertRedirect(route('verification.notice'));

        $this->assertModelExists($recipe);
    }

    public function test_unverified_host_gets_403_on_json_host_routes(): void
    {
        $host = User::factory()->host()->unverified()->withoutTwoFactor()->create();

        $this->actingAs($host)
            ->postJson(route('recipeImports.store'), ['url' => 'https://www.tiktok.com/@example/video/1'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Bitte bestätige zuerst deine E-Mail-Adresse.']);

        $this->assertDatabaseCount('recipe_imports', 0);
    }

    public function test_verified_user_and_host_are_not_affected(): void
    {
        $this->actingAsUser();
        $this->get(route('home'))->assertOk();

        $this->actingAsHost();
        $this->get(route('recipes.create'))->assertOk();
    }

    public function test_unverified_non_host_is_sent_to_verification_before_host_check(): void
    {
        $user = User::factory()->unverified()->withoutTwoFactor()->create();

        $this->actingAs($user)
            ->get(route('recipes.create'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verification_notice_is_shown_to_unverified_user(): void
    {
        $user = User::factory()->unverified()->withoutTwoFactor()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/VerifyEmail'));
    }

    public function test_settings_stay_reachable_for_unverified_user(): void
    {
        // Wer sich bei der Adresse vertippt hat, muss sie korrigieren koennen.
        $user = User::factory()->unverified()->withoutTwoFactor()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    public function test_verification_link_verifies_and_unlocks_home(): void
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

        Event::assertDispatched(Verified::class);
        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->actingAs($user->fresh())->get(route('home'))->assertOk();
    }

    public function test_profile_email_change_resets_verification_and_sends_new_mail(): void
    {
        Notification::fake();
        $user = $this->actingAsUser(['email' => 'alt@example.com']);

        $this->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'neu@example.com',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success', 'Profil gespeichert. Bitte bestätige deine neue E-Mail-Adresse über den Link, den wir dir geschickt haben.');

        $user->refresh();
        $this->assertSame('neu@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertCount(1);

        // Bis zur Bestaetigung sind Nutzer-Routen gesperrt
        $this->get(route('home'))->assertRedirect(route('verification.notice'));
    }

    public function test_profile_update_without_email_change_sends_nothing(): void
    {
        Notification::fake();
        $user = $this->actingAsUser();

        $this->patch(route('profile.update'), [
            'name' => 'Neuer Name',
            'email' => $user->email,
        ])->assertSessionHas('success', 'Profil gespeichert.');

        $this->assertNotNull($user->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_profile_page_requires_verification_and_shares_verification_state(): void
    {
        $user = User::factory()->unverified()->withoutTwoFactor()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Profile')
                ->where('mustVerifyEmail', true)
                // Profile.vue: Hinweis bei `mustVerifyEmail && user.email_verified_at === null`
                ->where('auth.user.email_verified_at', null)
            );
    }

    public function test_verification_link_can_be_resent(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->withoutTwoFactor()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
