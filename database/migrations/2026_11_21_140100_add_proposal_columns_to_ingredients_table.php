<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E18 / ADR-005: Zutaten-Vorschläge aus Community-Rezepten.
 *
 * `status = approved` (Default, alle bestehenden Zutaten) gehört zum Katalog;
 * `proposed` ist nur im einreichenden Rezept sichtbar und wird erst mit der
 * Freigabe des Rezepts übernommen. Unter SQLite baut der Fremdschlüssel
 * `proposed_by` die Tabelle neu auf (Fremdschlüsselprüfung dabei aus).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->string('status', 20)->default('approved');
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->index('status');
        });
    }

    /**
     * Rückweg nur ohne offene Vorschläge (sie kämen sonst ungeprüft in den
     * Katalog). Keine automatische Datenänderung.
     */
    public function down(): void
    {
        $proposed = DB::table('ingredients')->where('status', '!=', 'approved')->count();

        if ($proposed > 0) {
            throw new RuntimeException(
                "Rollback abgebrochen: {$proposed} vorgeschlagene Zutat(en) vorhanden. ".
                'Es werden keine Daten automatisch geändert.'
            );
        }

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropForeign(['proposed_by']);
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn(['status', 'proposed_by']);
        });
    }
};
