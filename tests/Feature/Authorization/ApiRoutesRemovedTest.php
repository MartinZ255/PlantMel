<?php

namespace Tests\Feature\Authorization;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * F-16 / Entscheidung E6 (Slice 1b): Die API-Routen aus routes/api.php wurden
 * entfernt. Sie waren ungenutzt, zeigten teils auf fehlende Methoden (500) und
 * waren ohne Session-Middleware mit echtem Login fuer niemanden bedienbar
 * (Charakterisierung bis Slice 0: ApiRouteAuthorizationTest).
 *
 * Dieser Test haelt fest, dass unter /api keine App-Route mehr registriert ist.
 * Wird bewusst wieder eine API-Route eingefuehrt, braucht sie einen eigenen
 * Guard, einen Eintrag in RouteRoleMatrixTest::API_ROUTES und eine Anpassung
 * dieses Tests.
 */
#[Group('F-16')]
class ApiRoutesRemovedTest extends TestCase
{
    public function test_no_app_route_is_registered_under_api_prefix(): void
    {
        $apiRoutes = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = ltrim($route->uri(), '/');

            if ($uri === 'api' || str_starts_with($uri, 'api/')) {
                $apiRoutes[] = implode('|', array_diff($route->methods(), ['HEAD'])).' '.$uri;
            }
        }

        $this->assertSame([], $apiRoutes, 'Unerwartete API-Route: '.implode(', ', $apiRoutes));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function formerApiEndpoints(): array
    {
        return [
            'GET api/categories' => ['GET', '/api/categories'],
            'GET api/rating-dimensions' => ['GET', '/api/rating-dimensions'],
            'POST api/recipes' => ['POST', '/api/recipes'],
            'PUT api/recipes/{recipe}' => ['PUT', '/api/recipes/1'],
            'DELETE api/recipes/{recipe}' => ['DELETE', '/api/recipes/1'],
        ];
    }

    #[DataProvider('formerApiEndpoints')]
    public function test_former_api_endpoint_returns_404(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertNotFound();
    }
}
