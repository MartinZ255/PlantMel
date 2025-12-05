<?php

use App\Http\Controllers\IngredientController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\RatingDimensionController;
use App\Http\Controllers\RecipeController;

Route::get('/categories', [CategoryController::class, 'index']);

Route::get('/rating-dimensions', [RatingDimensionController::class, 'index']);

Route::middleware(['auth', 'is_host'])->group(function () {
    Route::post('/recipes', [RecipeController::class, 'store']);
    Route::put('/recipes/{recipe}', [RecipeController::class, 'update']);
    Route::delete('/recipes/{recipe}', [RecipeController::class, 'destroy']);
});
