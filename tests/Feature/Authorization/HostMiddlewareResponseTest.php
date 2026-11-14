<?php

namespace Tests\Feature\Authorization;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Antwort von EnsureUserIsHost (app/Http/Middleware/EnsureUserIsHost.php)
 * fuer angemeldete Nicht-Hosts.
 *
 * Seit Slice 3 (F-68) rendert bootstrap/app.php (withExceptions) fuer
 * Nicht-JSON-Requests die Inertia-Seite `Error` mit der Meldung der Exception;
 * JSON-Requests behalten die Standard-JSON-Fehlerstruktur.
 */
class HostMiddlewareResponseTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'Nur Hosts dürfen diese Aktion ausführen.';

    public function test_inertia_request_of_non_host_gets_inertia_error_page_403(): void
    {
        $this->actingAsUser();
        $recipe = Recipe::factory()->create();

        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            // Passende Asset-Version mitsenden: Sonst antwortet Inertia mit 409
            // (Versionskonflikt), sobald ein gebautes Manifest existiert.
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get(route('recipes.edit', $recipe));

        $response->assertForbidden();
        $response->assertHeader('X-Inertia', 'true');
        $response->assertJsonPath('component', 'Error');
        $response->assertJsonPath('props.status', 403);
        $response->assertJsonPath('props.message', self::MESSAGE);
    }

    public function test_json_request_of_non_host_gets_json_403_with_message(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('categories.store'), ['name' => 'Verboten']);

        $response->assertForbidden();
        $response->assertExactJson(['message' => self::MESSAGE]);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_plain_browser_request_of_non_host_gets_error_page_403(): void
    {
        $this->actingAsUser();

        $response = $this->get(route('recipes.create'));

        $response->assertForbidden();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 403)
            ->where('message', self::MESSAGE)
        );
    }
}
