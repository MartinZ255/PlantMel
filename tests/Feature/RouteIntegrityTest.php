<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regressionstest zu F-16 (behoben in Slice 1b): Früher zeigten Routen auf
 * nicht vorhandene Controller-Methoden (IngredientController@show,
 * CategoryController@index, RecipeController@destroy) und lieferten 500.
 *
 * Hält künftig jede registrierte Route sauber: Jede Route mit
 * Controller-Aktion muss auf eine existierende Methode zeigen.
 */
#[Group('F-16')]
class RouteIntegrityTest extends TestCase
{
    public function test_every_controller_route_points_to_an_existing_method(): void
    {
        $broken = [];
        $checked = 0;

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $action = $route->getAction('uses');

            if (! is_string($action)) {
                continue; // Closures und Route::view/redirect ohne Controller
            }

            [$class, $method] = str_contains($action, '@')
                ? explode('@', $action, 2)
                : [$action, '__invoke'];

            $checked++;

            if (! class_exists($class)) {
                $broken[] = $this->describe($route, $action, 'Klasse fehlt');

                continue;
            }

            if (! method_exists($class, $method)) {
                $broken[] = $this->describe($route, $action, 'Methode fehlt');
            }
        }

        $this->assertGreaterThan(0, $checked, 'Es wurden keine Controller-Routen gefunden.');

        $this->assertSame(
            [],
            $broken,
            sprintf(
                "%d von %d Controller-Routen zeigen auf nicht vorhandene Methoden (F-16):\n  - %s",
                count($broken),
                $checked,
                implode("\n  - ", $broken),
            ),
        );
    }

    public function test_removed_dead_routes_are_no_longer_registered(): void
    {
        $this->assertFalse(Route::has('ingredients.show'));

        $this->get('/ingredients/1')->assertStatus(405); // nur noch PUT/DELETE (Host)
    }

    private function describe(RoutingRoute $route, string $action, string $reason): string
    {
        $methods = implode('|', array_diff($route->methods(), ['HEAD']));

        return sprintf(
            '%s /%s -> %s (%s%s)',
            $methods,
            ltrim($route->uri(), '/'),
            $action,
            $reason,
            $route->getName() ? ', Name: '.$route->getName() : '',
        );
    }
}
