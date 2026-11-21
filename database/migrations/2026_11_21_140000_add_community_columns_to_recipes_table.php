<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E18 / ADR-005: Community-Rezepte.
 *
 * Additiv: Bestehende Rezepte erhalten über die Spalten-Defaults
 * `type = host` und `status = approved` – es werden keine Zeilen per
 * UPDATE verändert. Die Fremdschlüssel (`user_id`, `reviewed_by`) lassen
 * Laravel unter SQLite die Tabelle neu aufbauen; dabei schaltet Laravel die
 * Fremdschlüsselprüfung ab, damit `DROP TABLE recipes` keine Kaskaden in
 * recipe_images, recipe_ingredients usw. auslöst
 * (tests/Feature/Community/CommunityMigrationTest.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            // host = Rezeptbuch des Hosts, community = von Nutzern eingereicht
            $table->string('type', 20)->default('host');
            // draft | pending | approved | rejected
            $table->string('status', 20)->default('approved');
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->index(['type', 'status']);
            $table->index('user_id');
        });
    }

    /**
     * Rückweg nur, solange es keine Community-Rezepte gibt: Sonst würden sie
     * nach dem Rollback als Host-Rezepte öffentlich erscheinen. Es werden
     * keine Daten automatisch geändert.
     */
    public function down(): void
    {
        $community = DB::table('recipes')->where('type', '!=', 'host')->count();

        if ($community > 0) {
            throw new RuntimeException(
                "Rollback abgebrochen: {$community} Community-Rezept(e) vorhanden. ".
                'Sie müssten zuerst gelöscht werden; es werden keine Daten automatisch geändert.'
            );
        }

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex(['type', 'status']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['reviewed_by']);
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn(['type', 'status', 'user_id', 'rejection_reason', 'submitted_at', 'reviewed_at', 'reviewed_by']);
        });
    }
};
