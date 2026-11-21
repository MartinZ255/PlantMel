<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * E17 / ADR-004: Zuordnung eines Google-Kontos zu einem PlantMel-Konto.
 *
 * Reihenfolge:
 * 1. gleiche google_id  -> dieses Konto
 * 2. gleiche E-Mail (ohne Groß-/Kleinschreibung) -> google_id verknüpfen
 *    (automatische Verknüpfung, Entscheidung des Auftraggebers)
 * 3. sonst neues Konto ohne Passwort und ohne Host-Recht
 *
 * Voraussetzung ist immer eine von Google als verifiziert gemeldete E-Mail.
 */
class GoogleLogin
{
    /** Ergebnis-Codes für abgelehnte Anmeldungen (ohne personenbezogene Daten loggbar). */
    public const REJECT_UNVERIFIED = 'email_not_verified';

    public const REJECT_INCOMPLETE = 'incomplete_profile';

    public const REJECT_LINKED_ELSEWHERE = 'linked_to_other_google_account';

    /**
     * Knopf und Routen sind nur aktiv, wenn Client-ID und Secret gesetzt sind.
     */
    public static function enabled(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    /**
     * Prüft die Google-Angaben. Liefert null, wenn sie verwendbar sind,
     * sonst einen der REJECT_*-Codes.
     */
    public function rejectionReason(SocialiteUser $googleUser): ?string
    {
        $id = (string) $googleUser->getId();
        $email = trim((string) $googleUser->getEmail());

        if ($id === '' || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return self::REJECT_INCOMPLETE;
        }

        if (! $this->emailIsVerified($googleUser)) {
            return self::REJECT_UNVERIFIED;
        }

        return null;
    }

    /**
     * Sucht, verknüpft oder legt das Konto an. Vorher rejectionReason() prüfen.
     *
     * @return array{0: User|null, 1: string|null} [Konto, Ablehnungsgrund]
     */
    public function resolveUser(SocialiteUser $googleUser): array
    {
        $googleId = (string) $googleUser->getId();
        $email = Str::lower(trim((string) $googleUser->getEmail()));

        return DB::transaction(function () use ($googleUser, $googleId, $email): array {
            $user = User::where('google_id', $googleId)->first();

            if ($user !== null) {
                return [$user, null];
            }

            $user = User::whereRaw('lower(email) = ?', [$email])->first();

            if ($user !== null) {
                // Bereits mit einem anderen Google-Konto verknüpft: nicht
                // stillschweigend umhängen.
                if ($user->google_id !== null) {
                    return [null, self::REJECT_LINKED_ELSEWHERE];
                }

                $user->forceFill(['google_id' => $googleId]);

                if ($user->email_verified_at === null) {
                    $user->forceFill(['email_verified_at' => now()]);
                }

                $user->save();

                return [$user, null];
            }

            $name = trim((string) $googleUser->getName());

            // is_host wird nicht gesetzt: Spalten-Default ist false (F-46),
            // wie bei CreateNewUser.
            $user = new User;
            $user->forceFill([
                'name' => Str::limit($name !== '' ? $name : Str::before($email, '@'), 255, ''),
                'email' => $email,
                'google_id' => $googleId,
                'email_verified_at' => now(),
                'password' => null,
            ])->save();

            event(new Registered($user));

            return [$user, null];
        });
    }

    /**
     * Google meldet die Bestätigung je nach Endpunkt als `email_verified`
     * (OpenID Connect / userinfo v3) oder `verified_email` (userinfo v2),
     * als Boolean oder als Zeichenkette "true".
     */
    private function emailIsVerified(SocialiteUser $googleUser): bool
    {
        $raw = method_exists($googleUser, 'getRaw') ? (array) $googleUser->getRaw() : [];
        $value = $raw['email_verified'] ?? $raw['verified_email'] ?? false;

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    }
}
