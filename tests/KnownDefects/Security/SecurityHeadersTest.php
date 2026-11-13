<?php

namespace Tests\KnownDefects\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Bekannter Defekt F-47 (nur der Teil im Code): Die App setzt keine
 * Security-Header (bootstrap/app.php:18-30, keine Header-Middleware).
 *
 * Sollverhalten: Antworten tragen X-Content-Type-Options: nosniff und einen
 * Clickjacking-Schutz (X-Frame-Options oder CSP frame-ancestors).
 *
 * Hinweis: Die Header koennen alternativ im Webserver (Nginx) gesetzt werden.
 * Faellt die Entscheidung so aus, entfaellt dieser Test und die Pruefung
 * gehoert in die Staging-/Server-Checkliste (RELEASE-CHECKLIST.md).
 */
#[Group('known-defect')]
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
}
