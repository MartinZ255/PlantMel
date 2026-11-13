<?php

namespace Tests\KnownDefects\Security;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Bekannter Defekt F-08: User implementiert MustVerifyEmail nicht
 * (app/Models/User.php:10), obwohl Features::emailVerification() aktiv ist
 * (config/fortify.php:149) und routes/web.php:40 'verified' nutzt. Die
 * Middleware laesst alle durch, es wird keine Verifizierungsmail verschickt.
 *
 * Sollverhalten (vorbehaltlich ADR, Variante "Interface implementieren"):
 * unverifizierte Nutzer werden auf verification.notice umgeleitet und nach der
 * Registrierung wird eine Verifizierungs-Notification versendet.
 * Ist-Zustand: tests/Feature/Authorization/AuthorizationGapsTest.php
 * (test_f08_unverified_user_reaches_home).
 */
#[Group('known-defect')]
#[Group('F-08')]
class EmailVerificationEnforcementTest extends TestCase
{
    use RefreshDatabase;

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

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
