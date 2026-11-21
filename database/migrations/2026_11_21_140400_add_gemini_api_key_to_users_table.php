<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E18 / ADR-005: eigener Gemini-API-Schlüssel je Nutzer für den
 * TikTok-Import. Gespeichert verschlüsselt (Cast `encrypted`, APP_KEY), daher
 * TEXT. Reines ADD COLUMN, kein Tabellen-Neuaufbau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('gemini_api_key')->nullable();
        });
    }

    public function down(): void
    {
        $keys = DB::table('users')->whereNotNull('gemini_api_key')->count();

        if ($keys > 0) {
            throw new RuntimeException(
                "Rollback abgebrochen: {$keys} Konto/Konten mit hinterlegtem Gemini-Schlüssel. ".
                'Nutzer müssen ihn zuerst löschen; es werden keine Daten automatisch geändert.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('gemini_api_key');
        });
    }
};
