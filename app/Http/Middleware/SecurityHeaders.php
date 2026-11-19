<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt Security-Header auf alle Antworten (Befund F-47).
 *
 * Global registriert (bootstrap/app.php), damit auch 404-Antworten ohne
 * passende Route, Fehlerseiten und Redirects die Header tragen.
 *
 * - Bereits gesetzte Header werden nie ueberschrieben.
 * - Die ausfuehrliche CSP laeuft nur als Report-Only (blockiert nichts).
 *   Erzwungen wird nur frame-ancestors, weil Browser diese Direktive in
 *   Report-Only ignorieren.
 * - HSTS nur bei HTTPS-Requests.
 *
 * Betrieb und Scharfschalten: docs/modernization/SECURITY-HEADERS.md
 */
class SecurityHeaders
{
    /**
     * HSTS-Laufzeit: 180 Tage. Bewusst ohne includeSubDomains/preload
     * (Betreiberentscheidung, siehe Betriebsnotiz).
     */
    public const HSTS_MAX_AGE = 15552000;

    /**
     * Schalter fuer die Report-Only-CSP (keine Env-Variable).
     */
    public const CSP_REPORT_ONLY_ENABLED = true;

    /**
     * Erzwungene Minimal-CSP: nur Clickjacking-Schutz.
     */
    public const ENFORCED_CSP = "frame-ancestors 'none'";

    /**
     * Ziel-Policy, zunaechst nur beobachtend. Seit Slice 4 (F-58) wird die
     * Schrift selbst gehostet; style-src und font-src brauchen nur 'self'.
     *
     * @var array<string, string>
     */
    public const REPORT_ONLY_CSP = [
        'default-src' => "'self'",
        'script-src' => "'self'",
        'style-src' => "'self'",
        'font-src' => "'self'",
        'img-src' => "'self' data: blob:",
        'connect-src' => "'self'",
        'object-src' => "'none'",
        'base-uri' => "'self'",
        'form-action' => "'self'",
    ];

    /**
     * Konservativ: Die App nutzt keine dieser Browser-Funktionen
     * (Grep in resources/ ohne Treffer, Stand Slice 1b).
     *
     * @var list<string>
     */
    public const PERMISSIONS_POLICY_DISABLED = [
        'accelerometer',
        'camera',
        'display-capture',
        'geolocation',
        'gyroscope',
        'magnetometer',
        'microphone',
        'midi',
        'payment',
        'usb',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->setIfMissing($response, 'X-Content-Type-Options', 'nosniff');
        $this->setIfMissing($response, 'X-Frame-Options', 'DENY');
        $this->setIfMissing($response, 'Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->setIfMissing($response, 'Permissions-Policy', self::permissionsPolicy());
        $this->setIfMissing($response, 'Content-Security-Policy', self::ENFORCED_CSP);

        if (self::CSP_REPORT_ONLY_ENABLED && ! Vite::isRunningHot()) {
            $this->setIfMissing($response, 'Content-Security-Policy-Report-Only', self::reportOnlyPolicy());
        }

        if ($request->isSecure()) {
            $this->setIfMissing($response, 'Strict-Transport-Security', 'max-age='.self::HSTS_MAX_AGE);
        }

        return $response;
    }

    public static function reportOnlyPolicy(): string
    {
        $parts = [];
        foreach (self::REPORT_ONLY_CSP as $directive => $sources) {
            $parts[] = $directive.' '.$sources;
        }

        return implode('; ', $parts);
    }

    public static function permissionsPolicy(): string
    {
        return implode(', ', array_map(
            static fn (string $feature): string => $feature.'=()',
            self::PERMISSIONS_POLICY_DISABLED,
        ));
    }

    private function setIfMissing(Response $response, string $name, string $value): void
    {
        if (! $response->headers->has($name)) {
            $response->headers->set($name, $value);
        }
    }
}
