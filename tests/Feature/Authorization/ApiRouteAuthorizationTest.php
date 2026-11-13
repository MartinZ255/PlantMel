<?php

namespace Tests\Feature\Authorization;

use App\Http\Middleware\EnsureUserIsHost;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Charakterisierung der API-Routen (routes/api.php), Ist-Zustand.
 *
 * Wichtig - Testumgebung vs. echter Request:
 * Die Gruppe 'api' enthaelt kein StartSession. Die Middleware 'auth' nutzt den
 * Standard-Guard 'web' (SessionGuard). In einem echten Request findet dieser
 * Guard ohne gestartete Session nie einen Nutzer -> die drei Schreibrouten sind
 * fuer niemanden nutzbar (Gast-Verhalten fuer alle).
 * actingAs() setzt den Nutzer dagegen direkt im Guard und umgeht die Session.
 * Deshalb kommen Nutzer und Host im Test an 'auth' vorbei; das ist KEIN
 * realistisches Verhalten, sondern nur eine Aussage ueber 'is_host' und den
 * Controller. test_real_session_login_is_not_recognized_by_api_routes bildet den
 * echten Ablauf (Login per Cookie, frischer Prozesszustand) nach.
 */
class ApiRouteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * [Methode, URI, Gast-Status (HTML), Gast-Status (JSON), Nutzer, Host].
     * Host DELETE = 500: RecipeController@destroy existiert nicht (F-16).
     *
     * @return array<string, array{0: string, 1: string, 2: int, 3: int, 4: int, 5: int}>
     */
    public static function apiWriteRoutes(): array
    {
        return [
            'POST api/recipes' => ['POST', '/api/recipes', 302, 401, 403, 302],
            'PUT api/recipes/{recipe}' => ['PUT', '/api/recipes/{id}', 302, 401, 403, 302],
            'DELETE api/recipes/{recipe}' => ['DELETE', '/api/recipes/{id}', 302, 401, 403, 500],
        ];
    }

    #[DataProvider('apiWriteRoutes')]
    public function test_api_write_route_for_guest(string $method, string $uri, int $guestHtml, int $guestJson): void
    {
        $recipe = Recipe::factory()->create();
        $url = str_replace('{id}', (string) $recipe->id, $uri);

        $html = $this->call($method, $url, $this->recipePayload());
        $html->assertStatus($guestHtml);
        $html->assertRedirect(route('login'));

        $json = $this->json($method, $url, $this->recipePayload());
        $json->assertStatus($guestJson);
        $json->assertJson(['message' => 'Unauthenticated.']);

        $this->assertDatabaseCount('recipes', 1);
        $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'title' => $recipe->title]);
    }

    #[DataProvider('apiWriteRoutes')]
    public function test_api_write_route_for_non_host_via_acting_as(string $method, string $uri, int $guestHtml, int $guestJson, int $userStatus): void
    {
        $recipe = Recipe::factory()->create();
        $this->actingAsUser();

        $this->json($method, str_replace('{id}', (string) $recipe->id, $uri), $this->recipePayload())
            ->assertStatus($userStatus)
            ->assertJson(['message' => 'Nur Hosts dürfen diese Aktion ausführen.']);

        $this->assertDatabaseCount('recipes', 1);
        $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'title' => $recipe->title]);
    }

    /**
     * Nur in der Testumgebung erreichbar (actingAs umgeht die fehlende Session).
     */
    #[DataProvider('apiWriteRoutes')]
    public function test_api_write_route_for_host_via_acting_as(string $method, string $uri, int $guestHtml, int $guestJson, int $userStatus, int $hostStatus): void
    {
        $recipe = Recipe::factory()->create();
        $this->actingAsHost();

        $this->call($method, str_replace('{id}', (string) $recipe->id, $uri), $this->recipePayload())
            ->assertStatus($hostStatus);
    }

    public function test_api_read_route_rating_dimensions_is_public(): void
    {
        $this->getJson('/api/rating-dimensions')
            ->assertOk()
            ->assertJson([]);
    }

    public function test_api_routes_run_without_session_middleware(): void
    {
        // Der HTTP-Kernel registriert die Middleware-Gruppen erst beim Aufloesen.
        $this->app->make(HttpKernel::class);

        $route = Route::getRoutes()->match(Request::create('/api/recipes', 'POST'));
        $middleware = app('router')->gatherRouteMiddleware($route);

        $this->assertSame(['api', 'auth', 'is_host'], $route->gatherMiddleware());
        $this->assertSame([SubstituteBindings::class], app('router')->getMiddlewareGroups()['api']);
        $this->assertSame([Authenticate::class, SubstituteBindings::class, EnsureUserIsHost::class], $middleware);
        $this->assertNotContains(StartSession::class, $middleware);
        $this->assertSame('web', config('auth.defaults.guard'));
    }

    /**
     * Echter Ablauf ohne actingAs: Host meldet sich ueber /login an, die Session
     * liegt in der (Test-)Datenbank, das Session-Cookie wird mitgeschickt. Vor
     * jedem Folgerequest wird der In-Memory-Zustand von Guard und Session
     * verworfen, wie es bei einem neuen PHP-Prozess der Fall waere.
     */
    public function test_real_session_login_is_not_recognized_by_api_routes(): void
    {
        config(['session.driver' => 'database']);

        $host = User::factory()->host()->withoutTwoFactor()->create();
        $recipe = Recipe::factory()->create();

        $login = $this->post(route('login.store'), [
            'email' => $host->email,
            'password' => 'password',
        ]);
        $login->assertSessionHasNoErrors();

        $cookieName = config('session.cookie');
        $sessionCookie = $login->getCookie($cookieName, decrypt: false)?->getValue();
        $this->assertNotNull($sessionCookie, 'Login hat kein Session-Cookie gesetzt.');

        // Kontrolle: Dieselbe Session wird von einer Web-Host-Route erkannt.
        $this->freshProcessState();
        $this->withUnencryptedCookie($cookieName, $sessionCookie)
            ->get(route('recipes.edit', $recipe))
            ->assertOk();

        // API-Route mit demselben Cookie: Guard sieht keinen Nutzer.
        $this->freshProcessState();
        $this->withUnencryptedCookie($cookieName, $sessionCookie)
            ->put('/api/recipes/'.$recipe->id, $this->recipePayload())
            ->assertRedirect(route('login'));

        $this->freshProcessState();
        $this->withUnencryptedCookie($cookieName, $sessionCookie)
            ->putJson('/api/recipes/'.$recipe->id, $this->recipePayload())
            ->assertUnauthorized();

        $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'title' => $recipe->title]);
    }

    private function freshProcessState(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }

    /**
     * @return array<string, mixed>
     */
    private function recipePayload(): array
    {
        return [
            'name' => 'API-Rezept',
            'ingredients' => [['amount' => '1', 'name' => 'API-Zutat']],
            'steps' => ['API-Schritt'],
        ];
    }
}
