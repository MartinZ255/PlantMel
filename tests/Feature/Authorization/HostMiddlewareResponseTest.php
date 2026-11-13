<?php

namespace Tests\Feature\Authorization;

use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Charakterisierung: Antwort von EnsureUserIsHost (app/Http/Middleware/EnsureUserIsHost.php:18-20)
 * fuer angemeldete Nicht-Hosts, Ist-Zustand.
 *
 * bootstrap/app.php registriert keinen eigenen Exception-Renderer: Inertia-Requests
 * bekommen deshalb die HTML-Fehlerseite von Laravel (keine Inertia-Antwort, kein
 * Modal), JSON-Requests die Standard-JSON-Fehlerstruktur.
 */
class HostMiddlewareResponseTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'Nur Hosts dürfen diese Aktion ausführen.';

    public function test_inertia_request_of_non_host_gets_plain_html_403(): void
    {
        $this->actingAsUser();
        $recipe = Recipe::factory()->create();

        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get(route('recipes.edit', $recipe));

        $response->assertForbidden();
        $response->assertHeaderMissing('X-Inertia');
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $response->assertSee(self::MESSAGE);
    }

    public function test_json_request_of_non_host_gets_json_403_with_message(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('categories.store'), ['name' => 'Verboten']);

        $response->assertForbidden();
        $response->assertExactJson(['message' => self::MESSAGE]);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_plain_browser_request_of_non_host_gets_html_403(): void
    {
        $this->actingAsUser();

        $response = $this->get(route('recipes.create'));

        $response->assertForbidden();
        $response->assertSee(self::MESSAGE);
    }
}
