<?php

use App\Http\Controllers\IngredientController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\CollectionController;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\RatingDimension;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Landing', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('landing');


Route::get('/rezepte', [RecipeController::class, 'index'])
    ->name('recipes.index');

Route::get('/rezept/{recipe}', [RecipeController::class, 'show'])
    ->name('recipes.show');


Route::get('/rezepte/erstellen', function () {
    $ratingDimensions = RatingDimension::orderBy('sort_order')
        ->get(['id', 'name', 'description']);
    $categories = Category::orderBy('sort_order')
        ->get(['id', 'name']);
    $ingredients = Ingredient::orderBy('name')
        ->get(['id', 'name']);

    return Inertia::render('recipes/Create', [
        'ratingDimensions' => $ratingDimensions,
        'categories' => $categories,
        'ingredients' => $ingredients,
    ]);
})->name('recipes.create');

Route::post('/recipes', [RecipeController::class, 'store'])
    ->name('recipes.store');




Route::get('/zutatensuche', [IngredientController::class, 'index'])
    ->name('IngredientSearch');



Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/home', function (Request $request) {
        return Inertia::render('Home');
    })->name('home');


    Route::get('/meineZutaten', function () {
        return Inertia::render('MyIngredients');
    })->name('myIngredients');


    Route::get('/sammlungen', [CollectionController::class, 'index'])
        ->name('collections.index');

    Route::get('/sammlungen/erstellen', [CollectionController::class, 'create'])
        ->name('collections.create');

    Route::post('/collections', [CollectionController::class, 'store'])
        ->name('collections.store');

    Route::get('/sammlung/{collection}', [CollectionController::class, 'show'])
        ->name('collections.show');

    Route::post('/collections/{collection}/recipes', [CollectionController::class, 'addRecipe'])
        ->name('collections.addRecipe');
});



require __DIR__.'/settings.php';
