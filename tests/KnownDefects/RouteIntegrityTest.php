<?php

namespace Tests\KnownDefects;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Bekannter Defekt F-16: Routen zeigen auf Controller-Methoden, die nicht existieren
 * (IngredientController@show, CategoryController@index, RecipeController@destroy).
 *
 * Sollverhalten: Jede registrierte Route mit Controller-Aktion zeigt auf eine
 * existierende, aufrufbare Methode. Dieser Test ist rot, bis F-16 behoben ist
 * (Slice 3); danach gehört er in die reguläre Feature-Suite.
 */
#[Group('known-defect')]
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
