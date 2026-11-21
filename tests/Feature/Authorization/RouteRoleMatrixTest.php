<?php

namespace Tests\Feature\Authorization;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeImport;
use App\Models\RecipeReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Charakterisierung: Rollen-Matrix ueber alle App-Routen (Ist-Zustand).
 *
 * Quelle der Listen: routes/web.php, routes/settings.php, routes/api.php
 * (seit Slice 1b leer).
 * Jede registrierte App-Route muss in GENAU EINER Liste stehen
 * (test_every_app_route_is_covered_by_exactly_one_matrix_list), damit eine neue,
 * ungetestete Route sofort auffaellt.
 *
 * Fachliche Ablaeufe (Rezept-CRUD, Sammlungen, Import) testen andere Klassen;
 * hier geht es nur um Gast / Nutzer / Host.
 */
class RouteRoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * routes/web.php, Gruppe ['auth', 'verified', 'is_host'].
     * Wert: [HTTP-Methode, gemessener Status fuer einen Host mit gueltigen Daten].
     *
     * @var array<string, array{0: string, 1: int}>
     */
    public const HOST_ROUTES = [
        'recipes.create' => ['GET', 200],
        'recipeImports.store' => ['POST', 200],
        'recipeImports.show' => ['GET', 200],
        'recipes.store' => ['POST', 302],
        'recipes.edit' => ['GET', 200],
        'recipes.update' => ['POST', 302],
        'recipes.delete' => ['DELETE', 302],
        'categories.store' => ['POST', 201],
        'categories.update' => ['PUT', 200],
        'categories.destroy' => ['DELETE', 200],
        'ingredients.store' => ['POST', 201],
        'ingredients.update' => ['PUT', 200],
        'ingredients.destroy' => ['DELETE', 200],
        // E18: Moderation der Community-Rezepte
        'moderation.index' => ['GET', 200],
        'moderation.approve' => ['POST', 302],
        'moderation.reject' => ['POST', 302],
        'moderation.hide' => ['POST', 302],
        'moderation.reports.resolve' => ['POST', 302],
        'moderation.ingredients.rename' => ['PATCH', 302],
        'moderation.ingredients.merge' => ['POST', 302],
    ];

    /**
     * routes/web.php:40-100, Gruppe ['auth', 'verified'].
     * Wert: [HTTP-Methode, gemessener Status fuer einen verifizierten Nutzer].
     *
     * @var array<string, array{0: string, 1: int}>
     */
    public const USER_ROUTES = [
        'home' => ['GET', 200],
        'myIngredients' => ['GET', 200],
        'collections.index' => ['GET', 200],
        'collections.create' => ['GET', 200],
        'collections.store' => ['POST', 302],
        'collections.show' => ['GET', 200],
        'collections.addRecipe' => ['POST', 302],
        'collections.removeRecipe' => ['DELETE', 302],
        'collections.update' => ['PATCH', 302],
        'collections.delete' => ['DELETE', 302],
        // E18: Community-Rezepte (jeder angemeldete Nutzer, eigene Rezepte)
        'community.mine' => ['GET', 200],
        'community.create' => ['GET', 200],
        'community.store' => ['POST', 302],
        'community.edit' => ['GET', 200],
        'community.update' => ['POST', 302],
        'community.destroy' => ['DELETE', 302],
        'community.rate' => ['POST', 302],
        'communityImports.store' => ['POST', 200],
        'communityImports.show' => ['GET', 200],
    ];

    /**
     * routes/settings.php, Gruppe ['auth'] (ohne 'verified').
     * Slice 3 (F-20): `appearance.edit` und `two-factor.show` entfallen;
     * TwoFactorDisabledTest haelt fest, dass keine two-factor-Route existiert.
     *
     * @var array<string, string>
     */
    public const SETTINGS_ROUTES = [
        'settings.index' => 'GET',
        'profile.edit' => 'GET',
        'profile.update' => 'PATCH',
        'profile.destroy' => 'DELETE',
        'user-password.edit' => 'GET',
        'user-password.update' => 'PUT',
        // E18: eigener Gemini-Schlüssel (KI-Import)
        'settings.ai.edit' => 'GET',
        'settings.ai.update' => 'PUT',
        'settings.ai.destroy' => 'DELETE',
        'settings.ai.check' => 'POST',
    ];

    /**
     * routes/web.php (oeffentlicher Teil), ohne Middleware ausser 'web'.
     * Wert: [HTTP-Methode, gemessener Status fuer einen Gast].
     *
     * @var array<string, array{0: string, 1: int}>
     */
    public const PUBLIC_ROUTES = [
        'landing' => ['GET', 200],
        'recipes.index' => ['GET', 200],
        'recipes.show' => ['GET', 200],
        'IngredientSearch' => ['GET', 200],
        // Slice 4 (F-10): Rechtstexte
        'legal.imprint' => ['GET', 200],
        'legal.privacy' => ['GET', 200],
        // E18: Community-Rezepte (nur freigegebene sichtbar)
        'community.index' => ['GET', 200],
        'community.show' => ['GET', 200],
    ];

    /**
     * E18: öffentlich, aber mit Rate-Limit (Meldeweg, auch für Gäste).
     * Wert: [HTTP-Methode, gemessener Status für einen Gast, Middleware].
     *
     * @var array<string, array{0: string, 1: int, 2: list<string>}>
     */
    public const THROTTLED_PUBLIC_ROUTES = [
        'community.report' => ['POST', 302, ['web', 'throttle:recipe-reports']],
    ];

    /**
     * routes/web.php, Gruppe ['guest', 'throttle:google-login'] (E17, Google-Anmeldung).
     * Gäste erreichen sie (ohne Konfiguration: Redirect auf /login, siehe
     * tests/Feature/Auth/GoogleLoginTest.php); angemeldete Nutzer leitet
     * `guest` auf /home.
     *
     * @var array<string, string>
     */
    public const GUEST_ROUTES = [
        'auth.google.redirect' => 'GET',
        'auth.google.callback' => 'GET',
    ];

    /**
     * routes/api.php: seit Slice 1b leer (F-16, E6). Kommt dort eine Route
     * hinzu, muss sie hier (Schluessel "METHODE uri" bzw. Name) mit Rollen-
     * Erwartung eingetragen werden; ApiRoutesRemovedTest schlaegt dann
     * ebenfalls an und ist bewusst anzupassen.
     *
     * @var list<string>
     */
    public const API_ROUTES = [];

    /**
     * Framework-interne Routen, die nicht aus den App-Routendateien stammen.
     * Fortify-Routen werden zusaetzlich ueber den Controller-Namespace
     * Laravel\Fortify\ ausgenommen.
     *
     * @var list<string>
     */
    public const FRAMEWORK_ROUTES = [
        'GET up',
        'storage.local',
        // Seit Laravel 12.7x: signierte Upload-URL der lokalen Disk (serve => true).
        // Ohne gueltige Signatur 403 - siehe tests/Feature/Security/StorageUploadRouteTest.php.
        'storage.local.upload',
    ];

    // ------------------------------------------------------------------
    // Data Provider
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function hostRoutes(): array
    {
        $cases = [];

        foreach (self::HOST_ROUTES as $name => [$method, $hostStatus]) {
            $cases[$name] = [$name, $method, $hostStatus];
        }

        return $cases;
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function userRoutes(): array
    {
        $cases = [];

        foreach (self::USER_ROUTES as $name => [$method, $userStatus]) {
            $cases[$name] = [$name, $method, $userStatus];
        }

        return $cases;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function settingsRoutes(): array
    {
        $cases = [];

        foreach (self::SETTINGS_ROUTES as $name => $method) {
            $cases[$name] = [$name, $method];
        }

        return $cases;
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function publicRoutes(): array
    {
        $cases = [];

        foreach (self::PUBLIC_ROUTES as $name => [$method, $guestStatus]) {
            $cases[$name] = [$name, $method, $guestStatus];
        }

        return $cases;
    }

    // ------------------------------------------------------------------
    // Host-Routen
    // ------------------------------------------------------------------

    #[DataProvider('hostRoutes')]
    public function test_host_route_redirects_guest_to_login(string $name, string $method): void
    {
        Queue::fake();
        $url = $this->hostRouteUrl($name, User::factory()->host()->withoutTwoFactor()->create());
        $payload = $this->hostPayload($name);
        $before = $this->databaseFingerprint();

        $response = $this->call($method, $url, $payload);

        $response->assertRedirect(route('login'));
        $this->assertSame($before, $this->databaseFingerprint(), "Gast hat bei {$name} Daten veraendert.");
    }

    #[DataProvider('hostRoutes')]
    public function test_host_route_forbids_non_host_without_changing_data(string $name, string $method): void
    {
        Queue::fake();
        $user = $this->actingAsUser();
        // Gueltige Nutzdaten: Fehlte die Middleware, wuerde sich die DB aendern.
        $url = $this->hostRouteUrl($name, $user);
        $payload = $this->hostPayload($name);
        $before = $this->databaseFingerprint();

        $response = $this->call($method, $url, $payload);

        $response->assertForbidden();
        $this->assertSame($before, $this->databaseFingerprint(), "Nicht-Host hat bei {$name} Daten veraendert.");
        Queue::assertNothingPushed();
    }

    #[DataProvider('hostRoutes')]
    public function test_host_route_is_reachable_for_host(string $name, string $method, int $expectedStatus): void
    {
        Queue::fake();
        $host = $this->actingAsHost();
        $url = $this->hostRouteUrl($name, $host);

        $response = $this->call($method, $url, $this->hostPayload($name));

        $this->assertNotRedirectedToLogin($response);
        $response->assertSessionHasNoErrors();
        $response->assertStatus($expectedStatus);
    }

    // ------------------------------------------------------------------
    // Nutzer-Routen
    // ------------------------------------------------------------------

    #[DataProvider('userRoutes')]
    public function test_user_route_redirects_guest_to_login(string $name, string $method): void
    {
        $owner = User::factory()->withoutTwoFactor()->create();
        $url = $this->userRouteUrl($name, $owner);
        $payload = $this->userPayload($name);
        $before = $this->databaseFingerprint();

        $response = $this->call($method, $url, $payload);

        $response->assertRedirect(route('login'));
        $this->assertSame($before, $this->databaseFingerprint(), "Gast hat bei {$name} Daten veraendert.");
    }

    #[DataProvider('userRoutes')]
    public function test_user_route_succeeds_for_verified_user(string $name, string $method, int $expectedStatus): void
    {
        Queue::fake();
        $user = $this->actingAsUser();
        $url = $this->userRouteUrl($name, $user);

        $response = $this->call($method, $url, $this->userPayload($name));

        $this->assertNotRedirectedToLogin($response);
        $response->assertSessionHasNoErrors();
        $response->assertStatus($expectedStatus);
    }

    // ------------------------------------------------------------------
    // Settings- und oeffentliche Routen
    // ------------------------------------------------------------------

    #[DataProvider('settingsRoutes')]
    public function test_settings_route_redirects_guest_to_login(string $name, string $method): void
    {
        $response = $this->call($method, route($name));

        $response->assertRedirect(route('login'));
    }

    #[DataProvider('publicRoutes')]
    public function test_public_route_is_reachable_for_guest(string $name, string $method, int $expectedStatus): void
    {
        $parameters = match ($name) {
            'recipes.show' => ['recipe' => Recipe::factory()->create()],
            'community.show' => ['recipe' => $this->communityRecipe(User::factory()->create())],
            default => [],
        };

        $response = $this->call($method, route($name, $parameters));

        $this->assertGuest();
        $response->assertStatus($expectedStatus);
    }

    public function test_throttled_public_routes_are_reachable_for_guest_and_carry_their_limit(): void
    {
        foreach (self::THROTTLED_PUBLIC_ROUTES as $name => [$method, $guestStatus, $middleware]) {
            $this->assertSame($middleware, $this->middlewareOf($name), $name);

            $recipe = $this->communityRecipe(User::factory()->create());
            $response = $this->call($method, route($name, ['recipe' => $recipe]), [
                'reason' => 'spam', 'good_faith' => '1',
            ]);

            $this->assertGuest();
            $response->assertSessionHasNoErrors();
            $response->assertStatus($guestStatus);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function guestRoutes(): array
    {
        $cases = [];

        foreach (self::GUEST_ROUTES as $name => $method) {
            $cases[$name] = [$name, $method];
        }

        return $cases;
    }

    #[DataProvider('guestRoutes')]
    public function test_guest_route_redirects_authenticated_user_home(string $name, string $method): void
    {
        $this->actingAsUser();
        $before = $this->databaseFingerprint();

        $response = $this->call($method, route($name));

        $response->assertRedirect(route('home'));
        $this->assertSame($before, $this->databaseFingerprint(), "Angemeldeter Nutzer hat bei {$name} Daten veraendert.");
    }

    #[DataProvider('guestRoutes')]
    public function test_guest_route_is_reachable_for_guest(string $name, string $method): void
    {
        // ohne Google-Konfiguration: Hinweis statt 500
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $response = $this->call($method, route($name));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------
    // Struktur: Middleware je Liste und Vollstaendigkeit
    // ------------------------------------------------------------------

    public function test_route_lists_match_the_middleware_declared_in_route_files(): void
    {
        foreach (array_keys(self::HOST_ROUTES) as $name) {
            $middleware = $this->middlewareOf($name);
            $this->assertContains('auth', $middleware, $name);
            // Slice 4 (F-08/E2): wirkt nur bei plantmel.require_email_verification = true
            $this->assertContains('verified', $middleware, $name);
            $this->assertContains('is_host', $middleware, $name);
        }

        foreach (array_keys(self::USER_ROUTES) as $name) {
            $middleware = $this->middlewareOf($name);
            $this->assertContains('auth', $middleware, $name);
            $this->assertContains('verified', $middleware, $name);
            $this->assertNotContains('is_host', $middleware, $name);
        }

        foreach (array_keys(self::SETTINGS_ROUTES) as $name) {
            $middleware = $this->middlewareOf($name);
            $this->assertContains('auth', $middleware, $name);
            $this->assertNotContains('is_host', $middleware, $name);
        }

        foreach (array_keys(self::PUBLIC_ROUTES) as $name) {
            $this->assertSame(['web'], $this->middlewareOf($name), $name);
        }

        foreach (array_keys(self::GUEST_ROUTES) as $name) {
            $this->assertSame(['web', 'guest', 'throttle:google-login'], $this->middlewareOf($name), $name);
        }
    }

    public function test_every_app_route_is_covered_by_exactly_one_matrix_list(): void
    {
        $lists = [
            'HOST_ROUTES' => array_keys(self::HOST_ROUTES),
            'USER_ROUTES' => array_keys(self::USER_ROUTES),
            'SETTINGS_ROUTES' => array_keys(self::SETTINGS_ROUTES),
            'PUBLIC_ROUTES' => array_keys(self::PUBLIC_ROUTES),
            'GUEST_ROUTES' => array_keys(self::GUEST_ROUTES),
            'THROTTLED_PUBLIC_ROUTES' => array_keys(self::THROTTLED_PUBLIC_ROUTES),
            'API_ROUTES' => self::API_ROUTES,
            'FRAMEWORK_ROUTES' => self::FRAMEWORK_ROUTES,
        ];

        $registered = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with((string) $route->getControllerClass(), 'Laravel\\Fortify\\')) {
                continue;
            }

            $registered[] = $this->routeKey($route);
        }

        $registered = array_values(array_unique($registered));

        $uncovered = [];
        $multiple = [];

        foreach ($registered as $key) {
            $hits = array_keys(array_filter($lists, fn (array $list) => in_array($key, $list, true)));

            if ($hits === []) {
                $uncovered[] = $key;
            } elseif (count($hits) > 1) {
                $multiple[] = $key.' ('.implode(', ', $hits).')';
            }
        }

        $listed = array_merge(...array_values($lists));

        // Sicherung: Mindestens die bekannten Kernrouten muessen erfasst sein,
        // sonst wuerde der Test bei einer leeren Routentabelle trivial gruen.
        $this->assertContains('recipes.index', $registered);
        $this->assertContains('recipes.create', $registered);
        $stale = array_values(array_diff($listed, $registered));

        $this->assertSame([], $uncovered, 'Route ohne Eintrag in der Rollen-Matrix: '.implode(', ', $uncovered));
        $this->assertSame([], $multiple, 'Route in mehreren Matrix-Listen: '.implode(', ', $multiple));
        $this->assertSame([], $stale, 'Matrix-Eintrag ohne registrierte Route: '.implode(', ', $stale));
    }

    // ------------------------------------------------------------------
    // Helfer
    // ------------------------------------------------------------------

    /**
     * Legt die fuer Route-Model-Binding noetigen Modelle an und liefert die URL.
     * Der Import gehoert immer $owner, damit ein 403 nur aus der Middleware
     * stammen kann (der Controller prueft zusaetzlich den Besitz).
     */
    private function hostRouteUrl(string $name, User $owner): string
    {
        $recipe = Recipe::factory()->create();
        $ingredient = Ingredient::factory()->create(); // in keinem Rezept verwendet
        $category = Category::factory()->create();
        $import = RecipeImport::factory()->done()->create(['user_id' => $owner->id]);

        // E18: wartendes Community-Rezept mit Vorschlag und offener Meldung
        $author = User::factory()->create();
        $community = $this->communityRecipe($author, Recipe::STATUS_PENDING);
        $proposal = new Ingredient(['name' => 'Matrix-Vorschlag']);
        $proposal->forceFill(['status' => Ingredient::STATUS_PROPOSED, 'proposed_by' => $author->id])->save();
        $report = RecipeReport::create(['recipe_id' => $community->id, 'reason' => 'spam']);

        $parameters = match ($name) {
            'moderation.approve', 'moderation.reject', 'moderation.hide' => ['recipe' => $community],
            'moderation.reports.resolve' => ['report' => $report],
            'moderation.ingredients.rename', 'moderation.ingredients.merge' => ['ingredient' => $proposal],
            'recipeImports.show' => ['recipeImport' => $import],
            'recipes.edit', 'recipes.update', 'recipes.delete' => ['recipe' => $recipe],
            'categories.update', 'categories.destroy' => ['category' => $category],
            'ingredients.update', 'ingredients.destroy' => ['ingredient' => $ingredient],
            default => [],
        };

        return route($name, $parameters);
    }

    /**
     * Gueltige Nutzdaten je Host-Route (keine Validierungsfehler).
     *
     * @return array<string, mixed>
     */
    private function hostPayload(string $name): array
    {
        $recipe = [
            'name' => 'Matrix-Rezept',
            'ingredients' => [['amount' => '1', 'name' => 'Matrix-Rezeptzutat']],
            'steps' => ['Matrix-Schritt'],
        ];

        return match ($name) {
            'recipeImports.store' => ['url' => 'https://www.tiktok.com/@example/video/7000000000000000'],
            'recipes.store', 'recipes.update' => $recipe,
            'categories.store', 'categories.update' => ['name' => 'Matrix-Kategorie'],
            'ingredients.store', 'ingredients.update' => ['name' => 'Matrix-Zutat'],
            // ADR-002: globales Löschen nur mit Bestätigung (sonst 409)
            'categories.destroy', 'ingredients.destroy' => ['confirmed' => 1],
            'moderation.reject' => ['reason' => 'Bitte Mengen ergänzen.'],
            'moderation.ingredients.rename' => ['name' => 'Matrix-Vorschlag umbenannt'],
            'moderation.ingredients.merge' => ['target' => Ingredient::factory()->create(['name' => 'Matrix-Katalog'])->name],
            default => [],
        };
    }

    private function userRouteUrl(string $name, User $owner): string
    {
        $recipe = Recipe::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $owner->id]);
        $collection->recipes()->attach($recipe->id);

        // E18: eigenes Community-Rezept bzw. fremdes, freigegebenes (Bewerten)
        $own = $this->communityRecipe($owner, Recipe::STATUS_PENDING);
        $foreign = $this->communityRecipe(User::factory()->create());
        $import = RecipeImport::factory()->done()->create(['user_id' => $owner->id]);
        if ($name === 'communityImports.store') {
            $owner->forceFill(['gemini_api_key' => 'AIza'.str_repeat('m', 35)])->save();
        }

        $parameters = match ($name) {
            'community.edit', 'community.update', 'community.destroy' => ['recipe' => $own],
            'community.rate' => ['recipe' => $foreign],
            'communityImports.show' => ['recipeImport' => $import],
            'collections.show', 'collections.addRecipe', 'collections.update', 'collections.delete' => ['collection' => $collection],
            'collections.removeRecipe' => ['collection' => $collection, 'recipe' => $recipe],
            default => [],
        };

        return route($name, $parameters);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(string $name): array
    {
        return match ($name) {
            'collections.store', 'collections.update' => ['name' => 'Matrix-Sammlung'],
            'collections.addRecipe' => ['recipe_id' => Recipe::factory()->create()->id],
            'community.store', 'community.update' => [
                'name' => 'Matrix-Community-Rezept',
                'ingredients' => [['amount' => '1', 'name' => 'Matrix-Community-Zutat']],
                'steps' => ['Matrix-Schritt'],
            ],
            'community.rate' => ['value' => 4],
            'communityImports.store' => ['url' => 'https://www.tiktok.com/@example/video/7000000000000000'],
            default => [],
        };
    }

    /**
     * Vollstaendiger Inhalt aller Tabellen; erkennt Inserts, Updates und Deletes.
     *
     * @return array<string, string>
     */
    private function databaseFingerprint(): array
    {
        $fingerprint = [];

        foreach (array_column(Schema::getTables(), 'name') as $table) {
            if ($table === 'migrations') {
                continue;
            }

            $fingerprint[$table] = json_encode(DB::table($table)->get()->all());
        }

        ksort($fingerprint);

        return $fingerprint;
    }

    /** E18: Community-Rezept mit Autor und Status (Standard: freigegeben). */
    private function communityRecipe(User $author, string $status = Recipe::STATUS_APPROVED): Recipe
    {
        $recipe = Recipe::factory()->create();
        $recipe->forceFill(['type' => Recipe::TYPE_COMMUNITY, 'status' => $status, 'user_id' => $author->id])->save();

        return $recipe;
    }

    private function assertNotRedirectedToLogin(TestResponse $response): void
    {
        $this->assertNotSame(403, $response->getStatusCode());

        if ($response->isRedirect()) {
            $this->assertNotSame(route('login'), $response->headers->get('Location'));
        }
    }

    /**
     * @return list<string>
     */
    private function middlewareOf(string $name): array
    {
        $route = Route::getRoutes()->getByName($name);
        $this->assertNotNull($route, "Route {$name} ist nicht registriert.");

        return $route->gatherMiddleware();
    }

    private function routeKey(RoutingRoute $route): string
    {
        if ($route->getName() !== null) {
            return $route->getName();
        }

        $methods = array_values(array_diff($route->methods(), ['HEAD']));

        return $methods[0].' '.ltrim($route->uri(), '/');
    }
}
