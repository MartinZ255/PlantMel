<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Markiert alle Bestandskonten ohne email_verified_at als verifiziert.
 *
 * F-08/E2: einmalig auf dem Server auszufuehren, BEVOR
 * REQUIRE_EMAIL_VERIFICATION eingeschaltet wird, damit bestehende Nutzer nicht
 * ausgesperrt werden (docs/modernization/MAIL-SETUP.md). Datenaenderung in
 * Produktion: vorher Backup. Keine Rueckfrage (skriptfaehig), Exit-Code 0.
 */
class MarkUsersVerified extends Command
{
    /**
     * @var string
     */
    protected $signature = 'users:mark-verified
        {--dry-run : Nur die Anzahl betroffener Konten anzeigen, nichts aendern}';

    /**
     * @var string
     */
    protected $description = 'Setzt email_verified_at fuer alle Konten ohne Verifizierung auf jetzt (mit --dry-run: nur zaehlen).';

    public function handle(): int
    {
        $query = User::query()->whereNull('email_verified_at');

        if ($this->option('dry-run')) {
            $count = $query->count();
            $this->info("Testlauf: {$count} Konto/Konten ohne Verifizierung. Es wurde nichts geändert.");

            return self::SUCCESS;
        }

        // Ein UPDATE ohne Model-Events: es geht nur um die Spalte, kein
        // Versand und keine Aenderung von updated_at.
        $count = $query->toBase()->update(['email_verified_at' => now()]);

        if ($count === 0) {
            $this->info('Keine Konten ohne Verifizierung. Nichts zu tun.');

            return self::SUCCESS;
        }

        $this->info("{$count} Konto/Konten als verifiziert markiert.");

        return self::SUCCESS;
    }
}
