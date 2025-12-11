<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\IngredientAlternativeController;
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

// Landing
Route::get('/', function () {
    return Inertia::render('Landing', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('landing');

// Öffentliche Rezept-Ansicht
Route::get('/rezepte', [RecipeController::class, 'index'])
    ->name('recipes.index');

Route::get('/rezept/{recipe}', [RecipeController::class, 'show'])
    ->name('recipes.show');

// Öffentliche Zutaten-Suche / Zutaten-Detail
Route::get('/zutatensuche', [IngredientController::class, 'index'])
    ->name('IngredientSearch');

Route::get('/ingredients/{ingredient}', [IngredientController::class, 'show'])
    ->name('ingredients.show');


// Authentifizierte, verifizierte Benutzer (Sammlungen etc.)
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

    Route::delete('/collections/{collection}/recipes/{recipe}', [CollectionController::class, 'removeRecipe'])
        ->name('collections.removeRecipe');

    Route::delete('/collections/{collection}', [CollectionController::class, 'delete'])
        ->name('collections.delete');
});

// Host-spezifische Aktionen (Rezepte, Kategorien, Zutaten, Alternativen)
Route::middleware(['auth', 'is_host'])->group(function () {

    // Rezepte anlegen / bearbeiten / löschen
    Route::get('/rezepte/erstellen', function () {
        $ratingDimensions = RatingDimension::orderBy('sort_order')
            ->get(['id', 'name', 'description']);
        $categories = Category::orderBy('sort_order')
            ->get(['id', 'name']);
        $ingredients = Ingredient::orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('recipes/Create', [
            'ratingDimensions' => $ratingDimensions,
            'categories'       => $categories,
            'ingredients'      => $ingredients,
        ]);
    })->name('recipes.create');

    Route::post('/recipes', [RecipeController::class, 'store'])
        ->name('recipes.store');

    Route::get('/rezept/{recipe}/bearbeiten', [RecipeController::class, 'edit'])
        ->name('recipes.edit');

    Route::post('/rezept/{recipe}', [RecipeController::class, 'update'])
        ->name('recipes.update');

    Route::delete('/rezept/{recipe}', [RecipeController::class, 'delete'])
        ->name('recipes.delete');


    // Kategorien
    Route::post('/categories', [CategoryController::class, 'store'])
        ->name('categories.store');

    Route::put('/categories/{category}', [CategoryController::class, 'update'])
        ->name('categories.update');

    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
        ->name('categories.destroy');

    // Zutaten
    Route::post('/ingredients', [IngredientController::class, 'store'])
        ->name('ingredients.store');

    Route::put('/ingredients/{ingredient}', [IngredientController::class, 'update'])
        ->name('ingredients.update');

    Route::delete('/ingredients/{ingredient}', [IngredientController::class, 'destroy'])
        ->name('ingredients.destroy');

    // Alternativzutaten
    Route::get(
        '/recipes/{recipe}/ingredients/{ingredient}/alternatives',
        [IngredientAlternativeController::class, 'index']
    )->name('ingredientAlternatives.index');

    Route::post(
        '/recipes/{recipe}/ingredients/{ingredient}/alternatives',
        [IngredientAlternativeController::class, 'store']
    )->name('ingredientAlternatives.store');

    Route::delete(
        '/ingredient-alternatives/{alternative}',
        [IngredientAlternativeController::class, 'destroy']
    )->name('ingredientAlternatives.destroy');
});

require __DIR__.'/settings.php';
