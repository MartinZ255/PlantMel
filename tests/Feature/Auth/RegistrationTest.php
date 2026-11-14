<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        // E1 (Slice 3): Ziel nach der Registrierung ist /home.
        $response->assertRedirect(route('home', absolute: false));
    }

    public function test_registration_validation_messages_are_german(): void
    {
        $this->post(route('register.store'), [
            'name' => '',
            'email' => 'keine-adresse',
            'password' => 'kurz',
            'password_confirmation' => 'anders',
        ])->assertSessionHasErrors([
            'name' => 'Name muss ausgefüllt werden.',
            'email' => 'E-Mail-Adresse muss eine gültige E-Mail-Adresse sein.',
            'password' => 'Passwort muss mindestens 8 Zeichen lang sein.',
        ]);

        $this->assertContains(
            'Die Bestätigung von Passwort stimmt nicht überein.',
            session('errors')->get('password'),
        );
        $this->assertGuest();
    }

    public function test_registration_rejects_taken_email_in_german(): void
    {
        User::factory()->create(['email' => 'vergeben@example.com']);

        $this->post(route('register.store'), [
            'name' => 'Zweites Konto',
            'email' => 'vergeben@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors([
            'email' => 'E-Mail-Adresse ist bereits vergeben.',
        ]);
    }
}
