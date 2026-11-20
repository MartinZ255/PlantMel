<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->withoutTwoFactor()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        // E1 (Slice 3): Ziel nach dem Login ist /home.
        $response->assertRedirect(route('home', absolute: false));
    }

    public function test_login_keeps_the_intended_url(): void
    {
        $user = User::factory()->create();

        $this->get(route('collections.index'))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('collections.index'));
    }

    public function test_authenticated_user_visiting_login_is_redirected_to_home(): void
    {
        $this->actingAsUser();

        $this->get(route('login'))->assertRedirect(route('home'));
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors([
            // F-42: deutsche Meldung aus lang/de/auth.php
            'email' => 'Diese Zugangsdaten stimmen nicht mit unseren Daten überein.',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        // E1: Logout endet auf der öffentlichen Landing-Seite, nicht auf /home.
        $response->assertRedirect(route('landing', absolute: false));
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }

    public function test_login_validation_messages_are_german(): void
    {
        $this->post(route('login.store'), ['email' => '', 'password' => ''])
            ->assertSessionHasErrors([
                'email' => 'E-Mail-Adresse muss ausgefüllt werden.',
                'password' => 'Passwort muss ausgefüllt werden.',
            ]);
    }
}
