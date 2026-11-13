<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Vergibt oder entzieht Host-Rechte (users.is_host).
 *
 * F-46: is_host ist nicht mass-assignable; dieses Command ist der vorgesehene
 * Weg, einen Host anzulegen. Es setzt das Flag gezielt per forceFill und
 * fragt nichts interaktiv ab (skriptfaehig).
 */
class MakeHost extends Command
{
    /**
     * @var string
     */
    protected $signature = 'user:make-host
        {email : E-Mail-Adresse des bestehenden Kontos}
        {--revoke : Host-Rechte entziehen statt vergeben}';

    /**
     * @var string
     */
    protected $description = 'Vergibt Host-Rechte an ein bestehendes Konto (mit --revoke: entzieht sie).';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $revoke = (bool) $this->option('revoke');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Kein Konto mit der E-Mail-Adresse „{$email}“ gefunden.");

            return self::FAILURE;
        }

        $target = ! $revoke;

        if ((bool) $user->is_host === $target) {
            $this->info($target
                ? "„{$email}“ ist bereits Host. Keine Änderung."
                : "„{$email}“ ist kein Host. Keine Änderung.");

            return self::SUCCESS;
        }

        $user->forceFill(['is_host' => $target])->save();

        $this->info($target
            ? "„{$email}“ hat jetzt Host-Rechte."
            : "„{$email}“ wurden die Host-Rechte entzogen.");

        return self::SUCCESS;
    }
}
