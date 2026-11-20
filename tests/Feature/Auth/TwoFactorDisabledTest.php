<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Fortify\TwoFactorAuthenticatable;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * F-20 (Slice 3): Zwei-Faktor-Authentifizierung ist bewusst abgeschaltet
 * (Entscheidung des Auftraggebers). Die Spalten two_factor_* bleiben in der
 * Datenbank (keine Migration), werden aber nicht mehr ausgewertet.
 */
#[Group('F-20')]
class TwoFactorDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_feature_is_disabled(): void
    {
        $this->assertFalse(Features::enabled(Features::twoFactorAuthentication()));
        $this->assertFalse(Features::canManageTwoFactorAuthentication());
    }

    public function test_no_two_factor_route_is_registered(): void
    {
        $twoFactorRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => str_contains((string) $route->getName(), 'two-factor')
                || str_contains($route->uri(), 'two-factor'))
            ->map(fn (RoutingRoute $route) => $route->getName() ?? $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $twoFactorRoutes);
    }

    public function test_former_two_factor_urls_return_not_found(): void
    {
        $this->get('/two-factor-challenge')->assertNotFound();

        $this->actingAsUser();
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->get('/settings/two-factor')
            ->assertNotFound();
        $this->get('/user/two-factor-qr-code')->assertNotFound();
    }

    public function test_user_with_legacy_two_factor_values_logs_in_without_challenge(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => encrypt('alter-schluessel'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('home', absolute: false));
        $response->assertSessionMissing('login.id');

        // Die Altwerte bleiben unangetastet in der Datenbank.
        $this->assertNotNull($user->fresh()->two_factor_secret);
    }

    public function test_user_model_no_longer_uses_two_factor_trait_but_hides_columns(): void
    {
        $this->assertNotContains(TwoFactorAuthenticatable::class, class_uses_recursive(User::class));

        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => encrypt('alter-schluessel'),
            'two_factor_recovery_codes' => encrypt('[]'),
        ])->save();

        $serialized = $user->fresh()->toArray();
        $this->assertArrayNotHasKey('two_factor_secret', $serialized);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $serialized);
    }

    public function test_factory_default_has_no_two_factor_values(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
    }
}
