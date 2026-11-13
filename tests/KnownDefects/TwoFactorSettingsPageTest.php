<?php

namespace Tests\KnownDefects;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Befund F-20: Die Route two-factor.show rendert die Inertia-Seite
 * settings/TwoFactor, die Vue-Datei existiert aber nicht.
 *
 * Unveraendert aus dem Starter-Kit uebernommen (vorher in
 * tests/Feature/Settings/TwoFactorAuthenticationTest.php). Die Tests pruefen
 * das Sollverhalten und sind rot, bis die Seite wiederhergestellt oder das
 * Feature bewusst deaktiviert ist (Entscheidung E4).
 */
#[Group('known-defect')]
#[Group('F-20')]
class TwoFactorSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_settings_page_can_be_rendered()
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withoutTwoFactor()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('two-factor.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/TwoFactor')
                ->where('twoFactorEnabled', false)
            );
    }

    public function test_two_factor_settings_page_does_not_requires_password_confirmation_when_disabled()
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $user = User::factory()->create();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => false,
        ]);

        $this->actingAs($user)
            ->get(route('two-factor.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/TwoFactor')
            );
    }
}
