<?php

use App\Http\Middleware\EnsureUserIsHost;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // F-47: global, damit auch 404/Fehlerseiten und API-Antworten die Header tragen.
        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'is_host' => EnsureUserIsHost::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // E18: Ein (auch ungültiger) Gemini-Schlüssel landet bei einem
        // Validierungsfehler nie als „old input“ in der Sitzung.
        // (ergänzt die Standardliste current_password/password/…)
        $exceptions->dontFlash('gemini_api_key');

        // F-68 (Slice 3): Fehlerseiten als Inertia-Seite `Error` statt roher
        // HTML-Seite (Muster aus der Inertia-Doku "Error handling").
        // - JSON-/axios-Requests behalten die JSON-Antwort.
        // - Lokal mit APP_DEBUG=true bleibt die Debug-Seite.
        // - 419 (CSRF/Sitzung abgelaufen) leitet mit Flash-Fehler zurueck.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $status = $response->getStatusCode();

            // Slice 4: Die `verified`-Middleware bricht JSON-/axios-Requests mit einem
            // fest verdrahteten englischen Text ab (EnsureEmailIsVerified). Nur bei
            // eingeschalteter E-Mail-Verifizierung erreichbar.
            if ($status === 403
                && $exception instanceof HttpExceptionInterface
                && $exception->getMessage() === 'Your email address is not verified.'
                && $request->expectsJson()
                && ! $request->header('X-Inertia')) {
                return response()->json(['message' => 'Bitte bestätige zuerst deine E-Mail-Adresse.'], 403);
            }

            if (! in_array($status, [403, 404, 419, 429, 500, 503], true)
                || $exception instanceof HttpResponseException
                || ($request->expectsJson() && ! $request->header('X-Inertia'))
                || (app()->isLocal() && config('app.debug'))) {
                return $response;
            }

            if ($status === 419) {
                return back()->with('error', 'Die Sitzung ist abgelaufen. Bitte versuche es erneut.');
            }

            $messages = [
                403 => 'Du hast keine Berechtigung für diese Seite.',
                404 => 'Die angeforderte Seite wurde nicht gefunden.',
                429 => 'Zu viele Anfragen. Bitte warte einen Moment und versuche es dann erneut.',
                500 => 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuche es später erneut.',
                503 => 'PlantMel wird gerade gewartet. Bitte versuche es in Kürze erneut.',
            ];

            $message = $messages[$status];

            // Nur bei 403 die (deutsche) Meldung aus abort(403, '…') zeigen;
            // Laravels englischer Standardtext fuer Policies wird ersetzt.
            if ($status === 403
                && $exception instanceof HttpExceptionInterface
                && ! in_array($exception->getMessage(), ['', 'This action is unauthorized.'], true)) {
                $message = $exception->getMessage();
            }

            try {
                // Ohne passende Route (404) oder im Wartungsmodus (503) lief die
                // web-Middleware nicht: geteilte Props und Asset-Version nachholen.
                if (Inertia::getShared('auth') === null) {
                    $inertiaMiddleware = app(HandleInertiaRequests::class);
                    Inertia::version(fn () => $inertiaMiddleware->version($request));
                    Inertia::share($inertiaMiddleware->share($request));
                }

                return Inertia::render('Error', [
                    'status' => $status,
                    'message' => $message,
                ])->toResponse($request)->setStatusCode($status);
            } catch (Throwable) {
                // Fehler beim Rendern der Fehlerseite (z. B. Datenbank weg):
                // urspruengliche Antwort ausliefern statt eines Folgefehlers.
                return $response;
            }
        });
    })->create();
