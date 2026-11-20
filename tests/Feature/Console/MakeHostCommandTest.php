<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * F-46 (Slice 1b): `php artisan user:make-host {email} {--revoke}` ist der
 * vorgesehene Weg, Host-Rechte zu vergeben oder zu entziehen, nachdem
 * is_host nicht mehr mass-assignable ist.
 */
#[Group('F-46')]
class MakeHostCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_grants_host_flag(): void
    {
        $user = User::factory()->create(['email' => 'kochin@example.com']);
        $other = User::factory()->create();

        $this->artisan('user:make-host', ['email' => 'kochin@example.com'])
            ->expectsOutput('„kochin@example.com“ hat jetzt Host-Rechte.')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->is_host);
        $this->assertFalse($other->fresh()->is_host);
    }

    public function test_revokes_host_flag(): void
    {
        $host = User::factory()->host()->create(['email' => 'host@example.com']);

        $this->artisan('user:make-host', ['email' => 'host@example.com', '--revoke' => true])
            ->expectsOutput('„host@example.com“ wurden die Host-Rechte entzogen.')
            ->assertSuccessful();

        $this->assertFalse($host->fresh()->is_host);
    }

    public function test_is_idempotent_and_reports_unchanged_state(): void
    {
        $host = User::factory()->host()->create(['email' => 'host@example.com']);
        $user = User::factory()->create(['email' => 'nutzer@example.com']);

        $this->artisan('user:make-host', ['email' => 'host@example.com'])
            ->expectsOutput('„host@example.com“ ist bereits Host. Keine Änderung.')
            ->assertSuccessful();

        $this->artisan('user:make-host', ['email' => 'nutzer@example.com', '--revoke' => true])
            ->expectsOutput('„nutzer@example.com“ ist kein Host. Keine Änderung.')
            ->assertSuccessful();

        $this->assertTrue($host->fresh()->is_host);
        $this->assertFalse($user->fresh()->is_host);
    }

    public function test_unknown_email_fails_without_changes(): void
    {
        $user = User::factory()->create();

        $this->artisan('user:make-host', ['email' => 'unbekannt@example.com'])
            ->expectsOutput('Kein Konto mit der E-Mail-Adresse „unbekannt@example.com“ gefunden.')
            ->assertFailed();

        $this->assertFalse($user->fresh()->is_host);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_granted_host_can_use_host_routes(): void
    {
        $user = User::factory()->withoutTwoFactor()->create(['email' => 'neu@example.com']);

        $this->actingAs($user)->get(route('recipes.create'))->assertForbidden();

        $this->artisan('user:make-host', ['email' => 'neu@example.com'])->assertSuccessful();

        $this->actingAs($user->fresh())->get(route('recipes.create'))->assertOk();
    }
}
