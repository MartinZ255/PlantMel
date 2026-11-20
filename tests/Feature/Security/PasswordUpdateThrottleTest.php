<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * routes/settings.php:20-22: PUT settings/password hat throttle:6,1.
 * Login-Throttle und "Konto loeschen verlangt Passwort" sind bereits in
 * tests/Feature/Auth/AuthenticationTest.php bzw.
 * tests/Feature/Settings/ProfileUpdateTest.php abgedeckt.
 */
class PasswordUpdateThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_update_is_throttled_after_six_attempts_per_minute(): void
    {
        $user = $this->actingAsUser();

        $attempt = fn () => $this->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        for ($i = 1; $i <= 6; $i++) {
            $attempt()->assertSessionHasErrors('current_password');
        }

        $attempt()->assertStatus(429);

        // Auch ein korrektes Passwort wird im Sperrfenster abgewiesen.
        $this->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(429);

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }
}
