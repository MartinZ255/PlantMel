<?php

namespace Tests\Feature\Auth;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * E17 / ADR-004: Konten ohne Passwort (angelegt über die Google-Anmeldung).
 */
class PasswordlessAccountTest extends TestCase
{
    use RefreshDatabase;

    private function passwordlessUser(array $attributes = []): User
    {
        $user = User::factory()->withoutTwoFactor()->create(array_merge(['email' => 'google@example.test'], $attributes));
        $user->forceFill(['password' => null, 'google_id' => 'google-123'])->save();

        return $user->refresh();
    }

    // ------------------------------------------------------------------
    // Passwort-Login
    // ------------------------------------------------------------------

    public function test_password_login_for_account_without_password_fails_normally(): void
    {
        $user = $this->passwordlessUser();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'irgendwas'])
            ->assertRedirect()
            ->assertSessionHasErrors(['email' => 'Diese Zugangsdaten stimmen nicht mit unseren Daten überein.']);

        $this->assertGuest();
    }

    public function test_password_login_with_empty_password_does_not_crash(): void
    {
        $user = $this->passwordlessUser();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => ''])
            ->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    // ------------------------------------------------------------------
    // Passwort vergessen
    // ------------------------------------------------------------------

    public function test_forgot_password_sets_a_password_for_account_without_password(): void
    {
        Notification::fake();
        $user = $this->passwordlessUser();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'neues-passwort',
                'password_confirmation' => 'neues-passwort',
            ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

            return true;
        });

        $user->refresh();
        $this->assertTrue(Hash::check('neues-passwort', $user->password));
        $this->assertSame('google-123', $user->google_id, 'Google-Verknüpfung bleibt.');

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'neues-passwort'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    // ------------------------------------------------------------------
    // Einstellungen: Passwort festlegen
    // ------------------------------------------------------------------

    public function test_password_page_shares_has_password(): void
    {
        $this->actingAs($this->passwordlessUser());
        $this->get(route('user-password.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('settings/Password')->where('hasPassword', false));

        $this->actingAsUser();
        $this->get(route('user-password.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('hasPassword', true));
    }

    public function test_password_can_be_set_without_current_password(): void
    {
        $user = $this->passwordlessUser();

        $this->actingAs($user)
            ->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'password' => 'neues-passwort',
                'password_confirmation' => 'neues-passwort',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user-password.edit'))
            ->assertSessionHas('success', 'Passwort festgelegt.');

        $this->assertTrue(Hash::check('neues-passwort', $user->refresh()->password));

        $this->get(route('user-password.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('hasPassword', true));
    }

    public function test_setting_a_password_still_validates_confirmation_and_rules(): void
    {
        $user = $this->passwordlessUser();

        $this->actingAs($user)
            ->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'password' => 'neues-passwort',
                'password_confirmation' => 'anders',
            ])
            ->assertSessionHasErrors('password')
            ->assertSessionDoesntHaveErrors('current_password');

        $this->assertNull($user->refresh()->password);
    }

    public function test_user_with_password_still_needs_current_password(): void
    {
        $user = $this->actingAsUser();

        $this->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'password' => 'neues-passwort',
                'password_confirmation' => 'neues-passwort',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    // ------------------------------------------------------------------
    // Konto löschen
    // ------------------------------------------------------------------

    public function test_profile_page_shares_has_password_and_has_google(): void
    {
        $this->actingAs($this->passwordlessUser());
        $this->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Profile')
                ->where('hasPassword', false)
                ->where('hasGoogle', true)
            );

        $this->actingAsUser();
        $this->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasPassword', true)
                ->where('hasGoogle', false)
            );
    }

    public function test_account_without_password_is_deleted_after_email_confirmation(): void
    {
        $user = $this->passwordlessUser();
        Collection::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['email' => '  Google@Example.TEST '])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('landing'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
        $this->assertSame(0, Collection::count());
    }

    public function test_account_without_password_is_not_deleted_with_wrong_email(): void
    {
        $user = $this->passwordlessUser();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['email' => 'fremd@example.test'])
            ->assertSessionHasErrors(['email' => 'Die E-Mail-Adresse stimmt nicht mit deinem Konto überein.'])
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
        $this->assertAuthenticatedAs($user);
    }

    public function test_account_without_password_needs_email_not_password(): void
    {
        $user = $this->passwordlessUser();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => ''])
            ->assertSessionHasErrors('email');

        $this->assertNotNull($user->fresh());
    }

    public function test_account_with_password_cannot_be_deleted_by_email(): void
    {
        $user = $this->actingAsUser(['email' => 'mitpasswort@example.test']);

        $this->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['email' => 'mitpasswort@example.test'])
            ->assertSessionHasErrors('password');

        $this->assertNotNull($user->fresh());
    }

    public function test_account_with_password_is_deleted_with_password(): void
    {
        $user = $this->actingAsUser();

        $this->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('landing'));

        $this->assertNull($user->fresh());
    }

    // ------------------------------------------------------------------
    // Profil: E-Mail-Änderung bei verknüpftem Google-Konto
    // ------------------------------------------------------------------

    public function test_email_change_keeps_google_link_and_google_login_still_works(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        $user = $this->passwordlessUser();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'neu@example.test'])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('neu@example.test', $user->email);
        $this->assertSame('google-123', $user->google_id);

        $this->post(route('logout'));
        $this->assertGuest();

        $googleUser = SocialiteUser::fake(['id' => 'google-123', 'email' => 'google@example.test', 'name' => 'X']);
        $googleUser->setRaw(['sub' => 'google-123', 'email' => 'google@example.test', 'email_verified' => true]);
        Socialite::fake('google', $googleUser);

        $this->get(route('auth.google.callback'))->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }
}
