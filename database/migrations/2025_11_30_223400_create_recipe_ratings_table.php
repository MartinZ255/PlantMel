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
        Schema::create('recipe_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')
                ->constrained('recipes')
                ->cascadeOnDelete();
            $table->foreignId('dimension_id')
                ->constrained('rating_dimensions')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('value'); // 1–5
            $table->timestamps();

            $table->unique(['recipe_id', 'dimension_id']);
            $table->index('recipe_id');
            $table->index('dimension_id');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_ratings');
    }
};
