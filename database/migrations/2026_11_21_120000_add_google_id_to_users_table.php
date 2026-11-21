<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E17 / ADR-004: Anmeldung mit Google.
 *
 * Additiv, keine Datenänderung:
 * - users.google_id: string, nullable, unique (Google "sub")
 * - users.password: nullable (Konten, die nur per Google angelegt wurden)
 *
 * SQLite: `->change()` baut die Tabelle neu auf (neue Tabelle, Daten kopieren,
 * alte löschen, umbenennen). Laravel schaltet dafür `foreign_keys` ab, damit
 * das DROP TABLE keine ON-DELETE-CASCADE-Kaskaden in comments, collections
 * und recipe_imports auslöst. Das PRAGMA wirkt nur außerhalb einer
 * Transaktion; SQLite-Migrationen laufen in Laravel ohne Transaktion
 * (SQLiteGrammar::$transactions = false). Abgesichert durch
 * tests/Feature/Auth/GoogleLoginMigrationTest.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->after('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('google_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Rückweg nur, solange kein Konto ohne Passwort existiert. Andernfalls
     * bricht der Rollback ab, statt Daten zu verändern oder an der
     * NOT-NULL-Bedingung zu scheitern (betroffene Konten müssen vorher ein
     * Passwort setzen, z. B. über "Passwort vergessen").
     */
    public function down(): void
    {
        $withoutPassword = DB::table('users')->whereNull('password')->count();

        if ($withoutPassword > 0) {
            throw new RuntimeException(
                "Rollback abgebrochen: {$withoutPassword} Konto/Konten ohne Passwort (users.password = null). ".
                'Diese Konten müssen zuerst ein Passwort festlegen; es werden keine Daten automatisch geändert.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_id');
        });
    }
};
