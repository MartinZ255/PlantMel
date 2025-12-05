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
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')
                ->constrained('recipes')
                ->cascadeOnDelete();
            $table->foreignId('ingredient_id')
                ->constrained('ingredients');
            $table->decimal('quantity', 10, 2)->nullable();
            $table->string('unit', 32)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index('recipe_id');
            $table->index('ingredient_id');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
    }
};
