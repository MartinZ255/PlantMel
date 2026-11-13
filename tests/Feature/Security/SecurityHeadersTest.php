<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Models\Recipe;
use App\Models\RecipeImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Regressionstest zu Befund F-47 (Teil im Code): Security-Header werden von
 * App\Http\Middleware\SecurityHeaders gesetzt (global in bootstrap/app.php).
 *
 * Sollverhalten: Antworten tragen X-Content-Type-Options: nosniff und einen
 * Clickjacking-Schutz (X-Frame-Options und CSP frame-ancestors); die
 * ausfuehrliche CSP laeuft als Report-Only; HSTS nur bei HTTPS.
 *
 * Betrieb und Abgleich mit Nginx: docs/modernization/SECURITY-HEADERS.md
 */
#[Group('F-47')]
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_response_sets_nosniff(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_landing_response_has_clickjacking_protection(): void
    {
        $response = $this->get(route('landing'))->assertOk();

        $frameOptions = strtoupper((string) $response->headers->get('X-Frame-Options'));
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertTrue(
            in_array($frameOptions, ['DENY', 'SAMEORIGIN'], true) || str_contains($csp, 'frame-ancestors'),
            'Weder X-Frame-Options (DENY/SAMEORIGIN) noch CSP frame-ancestors gesetzt.'
            .' Vorhanden: X-Frame-Options="'.$frameOptions.'", CSP="'.$csp.'"',
        );
    }

    public function test_public_page_carries_full_header_set(): void
    {
        $response = $this->get(route('recipes.index'))->assertOk();

        $this->assertBaseHeaders($response);
    }

    public function test_inertia_response_carries_headers(): void
    {
        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get(route('landing'));

        $response->assertOk()->assertHeader('X-Inertia', 'true');
        $this->assertBaseHeaders($response);
    }

    public function test_guest_redirect_to_login_carries_headers(): void
    {
        $response = $this->get(route('home'));

        $response->assertRedirect(route('login'));
        $this->assertBaseHeaders($response);
    }

    public function test_forbidden_response_carries_headers(): void
    {
        $this->actingAsUser();

        $response = $this->get(route('recipes.create'));

        $response->assertForbidden();
        $this->assertBaseHeaders($response);
    }

    public function test_forbidden_inertia_response_carries_headers(): void
    {
        $this->actingAsUser();
        $recipe = Recipe::factory()->create();

        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get(route('recipes.edit', $recipe));

        $response->assertForbidden();
        $this->assertBaseHeaders($response);
    }

    public function test_not_found_without_matching_route_carries_headers(): void
    {
        $response = $this->get('/diese-seite-gibt-es-nicht');

        $response->assertNotFound();
        $this->assertBaseHeaders($response);
    }

    public function test_json_response_of_host_endpoint_carries_headers(): void
    {
        $host = $this->actingAsHost();
        $import = RecipeImport::factory()->pending()->create(['user_id' => $host->id]);

        $response = $this->getJson(route('recipeImports.show', $import));

        $response->assertOk()->assertJsonPath('id', $import->id);
        $this->assertBaseHeaders($response);
    }

    public function test_hsts_is_missing_on_plain_http(): void
    {
        $this->get('http://localhost/')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_set_on_https_without_preload_or_subdomains(): void
    {
        $response = $this->get('https://localhost/')->assertOk();

        $response->assertHeader('Strict-Transport-Security', 'max-age='.SecurityHeaders::HSTS_MAX_AGE);
        $hsts = (string) $response->headers->get('Strict-Transport-Security');
        $this->assertStringNotContainsString('preload', $hsts);
        $this->assertStringNotContainsString('includeSubDomains', $hsts);
    }

    public function test_report_only_csp_contains_default_src_and_no_frame_ancestors(): void
    {
        $response = $this->get(route('landing'))->assertOk();

        $policy = (string) $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString('https://fonts.bunny.net', $policy);
        // Report-Only ignoriert frame-ancestors; die Direktive gehoert in den erzwungenen Header.
        $this->assertStringNotContainsString('frame-ancestors', $policy);
        $this->assertStringNotContainsString('report-uri', $policy);
    }

    public function test_enforced_csp_contains_only_frame_ancestors(): void
    {
        $response = $this->get(route('landing'))->assertOk();

        $this->assertSame(
            ["frame-ancestors 'none'"],
            $response->headers->all('content-security-policy'),
        );
    }

    public function test_report_only_csp_is_omitted_while_vite_dev_server_is_running(): void
    {
        $hotFile = tempnam(sys_get_temp_dir(), 'plantmel-hot-');
        file_put_contents($hotFile, 'http://localhost:5173');

        try {
            // Der Test-Fake aus withoutVite() ignoriert useHotFile(); im Hot-Modus
            // braucht das echte Vite kein Manifest.
            $this->withVite();
            Vite::useHotFile($hotFile);

            $response = $this->get(route('landing'))->assertOk();

            $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
            $response->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
        } finally {
            @unlink($hotFile);
        }
    }

    public function test_existing_headers_are_not_overwritten(): void
    {
        $response = (new SecurityHeaders)->handle(Request::create('/'), function (): Response {
            return new Response('ok', 200, [
                'X-Frame-Options' => 'SAMEORIGIN',
                'Content-Security-Policy' => "frame-ancestors 'self'",
                'Referrer-Policy' => 'no-referrer',
            ]);
        });

        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame(["frame-ancestors 'self'"], $response->headers->all('content-security-policy'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_binary_file_response_keeps_body_and_gets_headers(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'plantmel-img-');
        file_put_contents($file, 'bilddaten');

        try {
            $response = (new SecurityHeaders)->handle(
                Request::create('/storage/x.jpg'),
                fn (): Response => new BinaryFileResponse($file),
            );

            $this->assertInstanceOf(BinaryFileResponse::class, $response);
            $this->assertSame($file, $response->getFile()->getPathname());
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

            ob_start();
            $response->sendContent();
            $this->assertSame('bilddaten', ob_get_clean());
        } finally {
            @unlink($file);
        }
    }

    public function test_streamed_response_keeps_body_and_gets_headers(): void
    {
        $response = (new SecurityHeaders)->handle(
            Request::create('/storage/x.txt'),
            fn (): Response => new StreamedResponse(function (): void {
                echo 'gestreamt';
            }),
        );

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        ob_start();
        $response->sendContent();
        $this->assertSame('gestreamt', ob_get_clean());
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertBaseHeaders(TestResponse $response): void
    {
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
        $response->assertHeader('Content-Security-Policy-Report-Only', SecurityHeaders::reportOnlyPolicy());

        $permissions = (string) $response->headers->get('Permissions-Policy');
        foreach (['camera=()', 'microphone=()', 'geolocation=()', 'payment=()'] as $feature) {
            $this->assertStringContainsString($feature, $permissions);
        }
    }
}
