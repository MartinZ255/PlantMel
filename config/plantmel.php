<?php

return [

    /*
    |--------------------------------------------------------------------------
    | E-Mail-Verifizierung erzwingen (F-08, Entscheidung E2)
    |--------------------------------------------------------------------------
    |
    | Aus (Default): Die App verhaelt sich wie vor Slice 4. Es wird keine
    | Verifizierungsmail verschickt und die Middleware `verified` laesst alle
    | angemeldeten Nutzer durch (App\Models\User::hasVerifiedEmail()).
    |
    | An: Registrierung und E-Mail-Aenderung verschicken eine
    | Verifizierungsmail; unverifizierte Nutzer werden von Nutzer- und
    | Host-Routen auf `verification.notice` umgeleitet.
    |
    | Erst einschalten, wenn der Mailversand funktioniert und Bestandskonten
    | mit `php artisan users:mark-verified` markiert wurden
    | (docs/modernization/MAIL-SETUP.md).
    |
    */

    'require_email_verification' => (bool) env('REQUIRE_EMAIL_VERIFICATION', false),

];
