<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ingredient_alternatives', function (Blueprint $table) {
            $table->id();

            // NEU: recipe_id zuerst definieren
            $table->foreignId('recipe_id')
                ->constrained('recipes')
                ->cascadeOnDelete();

            $table->foreignId('ingredient_id')
                ->constrained('ingredients')
                ->cascadeOnDelete();

            $table->foreignId('alternative_ingredient_id')
                ->constrained('ingredients');

            $table->string('note', 255)->nullable();
            $table->timestamps();

            // eindeutig pro Rezept + Basiszutat + Alternative
            $table->unique(['recipe_id', 'ingredient_id', 'alternative_ingredient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_alternatives');
    }
};
