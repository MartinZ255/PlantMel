<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * F-08/E2 (Slice 4): Diese Starter-Tests pruefen den Ablauf bei
     * EINGESCHALTETER Verifizierung. Default (AUS): EmailVerificationDisabledTest.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['plantmel.require_email_verification' => true]);
    }

    public function test_sends_verification_notification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        // Fortify antwortet mit back(); ohne Referer ist das die Startseite.
        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('landing'))
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_does_not_send_verification_notification_if_email_is_verified(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('home', absolute: false));

        Notification::assertNothingSent();
    }
}
