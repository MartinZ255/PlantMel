<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * F-08/E2 (Slice 4): `php artisan users:mark-verified {--dry-run}` markiert
 * Bestandskonten vor dem Einschalten der Verifizierung als verifiziert.
 */
#[Group('F-08')]
class MarkUsersVerifiedCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_all_unverified_users_and_reports_count(): void
    {
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 11, 14)->setTime(12, 0, 0));

        $verifiedAt = now()->subYear();
        $verified = User::factory()->create(['email_verified_at' => $verifiedAt]);
        $unverified = User::factory()->unverified()->count(2)->create();

        $this->artisan('users:mark-verified')
            ->expectsOutput('2 Konto/Konten als verifiziert markiert.')
            ->assertExitCode(0);

        foreach ($unverified as $user) {
            $this->assertSame('2026-11-14 12:00:00', $user->fresh()->email_verified_at->format('Y-m-d H:i:s'));
        }

        // Bereits verifizierte Konten behalten ihr Datum
        $this->assertSame($verifiedAt->format('Y-m-d H:i:s'), $verified->fresh()->email_verified_at->format('Y-m-d H:i:s'));
        $this->assertSame(0, User::whereNull('email_verified_at')->count());
        Notification::assertNothingSent();
    }

    public function test_dry_run_only_reports_count(): void
    {
        User::factory()->unverified()->count(3)->create();
        User::factory()->create();

        $this->artisan('users:mark-verified', ['--dry-run' => true])
            ->expectsOutput('Testlauf: 3 Konto/Konten ohne Verifizierung. Es wurde nichts geändert.')
            ->assertExitCode(0);

        $this->assertSame(3, User::whereNull('email_verified_at')->count());
    }

    public function test_nothing_to_do(): void
    {
        User::factory()->create();

        $this->artisan('users:mark-verified')
            ->expectsOutput('Keine Konten ohne Verifizierung. Nichts zu tun.')
            ->assertExitCode(0);

        $this->artisan('users:mark-verified', ['--dry-run' => true])
            ->expectsOutput('Testlauf: 0 Konto/Konten ohne Verifizierung. Es wurde nichts geändert.')
            ->assertExitCode(0);
    }

    public function test_marked_users_pass_verification_when_switch_is_on(): void
    {
        $user = User::factory()->unverified()->withoutTwoFactor()->create();

        $this->artisan('users:mark-verified')->assertExitCode(0);

        config(['plantmel.require_email_verification' => true]);

        $this->actingAs($user->fresh())->get(route('home'))->assertOk();
    }

    public function test_does_not_touch_updated_at(): void
    {
        $this->travelTo(now()->subDay());
        $user = User::factory()->unverified()->create();
        $updatedAt = $user->fresh()->updated_at->format('Y-m-d H:i:s');
        $this->travelBack();

        $this->artisan('users:mark-verified')->assertExitCode(0);

        $this->assertSame($updatedAt, $user->fresh()->updated_at->format('Y-m-d H:i:s'));
    }
}
