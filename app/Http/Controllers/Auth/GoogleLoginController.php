<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleLogin;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Fortify;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * E17 / ADR-004: Anmeldung und Registrierung mit Google (Laravel Socialite).
 *
 * Routen (routes/web.php, Middleware guest + throttle:google-login):
 * - GET /auth/google/redirect  -> Weiterleitung zu Google
 * - GET /auth/google/callback  -> Rückkehr von Google
 *
 * Logs enthalten nur Ereignis und Grund, keine E-Mail, keinen Namen und
 * keine Google-ID.
 */
class GoogleLoginController extends Controller
{
    private const SESSION_REMEMBER = 'google_login.remember';

    /** Scopes: nur die für die Anmeldung nötigen Angaben. */
    private const SCOPES = ['openid', 'email', 'profile'];

    public function redirect(Request $request): RedirectResponse|SymfonyRedirectResponse
    {
        if (! GoogleLogin::enabled()) {
            return $this->unavailable();
        }

        // "Angemeldet bleiben" von der Login-Seite (?remember=1) über den
        // Umweg zu Google hinweg merken.
        $request->session()->put(self::SESSION_REMEMBER, $request->boolean('remember'));

        return Socialite::driver('google')
            ->setScopes(self::SCOPES)
            ->redirect();
    }

    public function callback(Request $request, GoogleLogin $googleLogin): RedirectResponse
    {
        if (! GoogleLogin::enabled()) {
            return $this->unavailable();
        }

        $remember = (bool) $request->session()->pull(self::SESSION_REMEMBER, false);

        // Abbruch auf der Google-Seite, z. B. ?error=access_denied
        if ($request->filled('error')) {
            $error = (string) $request->query('error');
            $this->logFailure($error === 'access_denied' ? 'access_denied' : 'provider_error');

            return $this->failed($error === 'access_denied'
                ? 'Die Anmeldung mit Google wurde abgebrochen.'
                : 'Google hat die Anmeldung nicht bestätigt. Bitte versuche es erneut.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            $this->logFailure('invalid_state');

            return $this->failed('Die Anmeldung mit Google ist abgelaufen oder ungültig. Bitte versuche es erneut.');
        } catch (Throwable $e) {
            $this->logFailure('provider_exception', $e);

            return $this->failed('Die Anmeldung mit Google hat nicht geklappt. Bitte versuche es später erneut.');
        }

        $reason = $googleLogin->rejectionReason($googleUser);

        if ($reason === null) {
            try {
                [$user, $reason] = $googleLogin->resolveUser($googleUser);
            } catch (UniqueConstraintViolationException $e) {
                // gleichzeitige Erstanmeldung derselben Person
                $this->logFailure('unique_violation', $e);

                return $this->failed('Die Anmeldung mit Google hat nicht geklappt. Bitte versuche es erneut.');
            }
        }

        if ($reason !== null) {
            $this->logFailure($reason);

            return $this->failed(match ($reason) {
                GoogleLogin::REJECT_UNVERIFIED => 'Deine E-Mail-Adresse ist bei Google nicht bestätigt. Bitte bestätige sie bei Google oder registriere dich mit E-Mail und Passwort.',
                GoogleLogin::REJECT_LINKED_ELSEWHERE => 'Dein PlantMel-Konto ist bereits mit einem anderen Google-Konto verknüpft. Bitte melde dich mit diesem Google-Konto oder mit deinem Passwort an.',
                default => 'Google hat keine verwendbare E-Mail-Adresse übermittelt. Bitte registriere dich mit E-Mail und Passwort.',
            });
        }

        Auth::guard(config('fortify.guard', 'web'))->login($user, $remember);
        $request->session()->regenerate();

        // wie der normale Login (Fortify LoginResponse): intended, sonst /home (E1)
        return redirect()->intended(Fortify::redirects('login'));
    }

    private function unavailable(): RedirectResponse
    {
        return redirect()->route('login')
            ->with('error', 'Die Anmeldung mit Google ist derzeit nicht verfügbar. Bitte melde dich mit E-Mail und Passwort an.');
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }

    /**
     * Nur Grund und Ausnahme-Klasse; Ausnahmetexte von Google/Guzzle können
     * Antwortinhalte enthalten und werden deshalb nicht geloggt.
     */
    private function logFailure(string $reason, ?Throwable $e = null): void
    {
        Log::warning('Google-Anmeldung fehlgeschlagen', array_filter([
            'reason' => $reason,
            'exception' => $e !== null ? $e::class : null,
        ]));
    }
}
