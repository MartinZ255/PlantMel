<?php

namespace Tests\Feature\Security;

use App\Models\Recipe;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CSRF-Grundlage der Web-Gruppe.
 *
 * Laravel 12 ueberspringt die Pruefung in Tests: VerifyCsrfToken::handle() fragt
 * runningUnitTests() ab (true bei APP_ENV=testing in der Konsole). Fuer diese
 * Tests wird die Middleware im Container durch eine Unterklasse ersetzt, die
 * NUR diese Testabkuerzung abschaltet; Token-Vergleich, Ausnahmen und Cookie-
 * Logik bleiben die des Frameworks. Der Router loest Middleware ueber den
 * Container auf, deshalb greift die Instanz-Bindung. Die Positiv-Kontrolle mit
 * gueltigem Token belegt, dass kein Scheintest vorliegt.
 *
 * Slice 3 (F-68): Ein abgelehnter Nicht-JSON-Request (Status 419) wird in
 * bootstrap/app.php in einen Redirect zurueck mit Flash-`error` umgewandelt
 * (Inertia-Empfehlung); JSON-Requests behalten 419.
 */
class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    private const EXPIRED = 'Die Sitzung ist abgelaufen. Bitte versuche es erneut.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    /**
     * Zustandsaendernde Web-Routen je Rolle (Stichprobe aus allen Gruppen).
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function stateChangingRoutes(): array
    {
        return [
            'collections.store (Nutzer)' => ['user', 'POST', 'collections.store'],
            'profile.update (Nutzer)' => ['user', 'PATCH', 'profile.update'],
            'profile.destroy (Nutzer)' => ['user', 'DELETE', 'profile.destroy'],
            'user-password.update (Nutzer)' => ['user', 'PUT', 'user-password.update'],
            'categories.store (Host)' => ['host', 'POST', 'categories.store'],
            'recipes.store (Host)' => ['host', 'POST', 'recipes.store'],
            'logout (Nutzer)' => ['user', 'POST', 'logout'],
            'login (Gast)' => ['guest', 'POST', 'login.store'],
            'register (Gast)' => ['guest', 'POST', 'register.store'],
        ];
    }

    #[DataProvider('stateChangingRoutes')]
    public function test_state_changing_web_route_rejects_request_without_csrf_token(string $role, string $method, string $name): void
    {
        match ($role) {
            'user' => $this->actingAsUser(),
            'host' => $this->actingAsHost(),
            default => null,
        };

        $before = [
            'users' => DB::table('users')->count(),
            'collections' => DB::table('collections')->count(),
            'categories' => DB::table('categories')->count(),
            'recipes' => DB::table('recipes')->count(),
        ];

        $this->from(route('landing'))
            ->call($method, route($name), ['name' => 'CSRF', 'email' => 'csrf@example.com'])
            ->assertRedirect(route('landing'))
            ->assertSessionHas('error', self::EXPIRED)
            ->assertSessionMissing('success');

        $this->assertSame($before, [
            'users' => DB::table('users')->count(),
            'collections' => DB::table('collections')->count(),
            'categories' => DB::table('categories')->count(),
            'recipes' => DB::table('recipes')->count(),
        ]);
    }

    public function test_wrong_csrf_token_is_rejected(): void
    {
        $this->actingAsUser();

        $this->withSession(['_token' => 'richtiges-token'])
            ->post(route('collections.store'), ['_token' => 'falsches-token', 'name' => 'CSRF'])
            ->assertRedirect()
            ->assertSessionHas('error', self::EXPIRED);

        $this->assertDatabaseCount('collections', 0);
    }

    public function test_json_request_without_token_keeps_status_419(): void
    {
        $this->actingAsHost();

        $this->postJson(route('categories.store'), ['name' => 'CSRF'])
            ->assertStatus(419)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_valid_csrf_token_in_body_is_accepted(): void
    {
        $this->actingAsUser();

        $this->withSession(['_token' => 'richtiges-token'])
            ->post(route('collections.store'), ['_token' => 'richtiges-token', 'name' => 'CSRF'])
            ->assertRedirect(route('collections.index'));

        $this->assertDatabaseCount('collections', 1);
    }

    public function test_valid_csrf_token_in_header_is_accepted(): void
    {
        $this->actingAsHost();

        $this->withSession(['_token' => 'richtiges-token'])
            ->withHeader('X-CSRF-TOKEN', 'richtiges-token')
            ->postJson(route('categories.store'), ['name' => 'CSRF'])
            ->assertCreated();
    }

    public function test_reading_requests_do_not_need_a_token(): void
    {
        $this->get(route('recipes.show', Recipe::factory()->create()))->assertOk();
    }

    public function test_no_web_uri_is_excluded_from_csrf_check(): void
    {
        $middleware = $this->app->make(ValidateCsrfToken::class);

        $this->assertSame([], (fn () => $this->except)->call($middleware));
        $this->assertSame([], (fn () => static::$neverVerify)->call($middleware));
    }
}
