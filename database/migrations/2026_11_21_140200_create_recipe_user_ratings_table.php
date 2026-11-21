<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E18 / ADR-005: Geschmacks-Bewertung (1–5) von Community-Rezepten durch
 * angemeldete Nutzer, je Nutzer und Rezept genau eine. Host-Rezepte
 * behalten ihre Bewertung in `recipe_ratings` (E14, unverändert).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_user_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('value'); // 1–5
            $table->timestamps();

            $table->unique(['recipe_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        $rows = DB::table('recipe_user_ratings')->count();

        if ($rows > 0) {
            throw new RuntimeException("Rollback abgebrochen: {$rows} Bewertung(en) in recipe_user_ratings vorhanden.");
        }

        Schema::dropIfExists('recipe_user_ratings');
    }
};
