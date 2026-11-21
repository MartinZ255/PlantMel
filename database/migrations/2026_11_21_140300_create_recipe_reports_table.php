<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E18 / ADR-005: Meldungen zu Community-Rezepten (Meldeweg nach Art. 16 DSA).
 * Keine IP-Adresse gespeichert (Datensparsamkeit); das Rate-Limit läuft
 * über den Cache.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 40);
            $table->text('message')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('status', 20)->default('open'); // open | resolved
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'recipe_id']);
        });
    }

    public function down(): void
    {
        $rows = DB::table('recipe_reports')->count();

        if ($rows > 0) {
            throw new RuntimeException("Rollback abgebrochen: {$rows} Meldung(en) in recipe_reports vorhanden.");
        }

        Schema::dropIfExists('recipe_reports');
    }
};
