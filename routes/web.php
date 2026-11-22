<?php

use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CommunityRecipeController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\RecipeImportController;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Services\TasteRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Laravel\Fortify\Features;

// Landing
Route::get('/', function () {
    // Gate C1b: bis zu 6 Rezepte MIT Bild, neueste zuerst, für die Startseite.
    // Bild-URL wie auf der Rezeptliste über die Disk `public` (erstes Bild).
    // Die öffentliche Startseite bleibt auch ohne Datenbank erreichbar: Fehler
    // werden protokolliert, die Seite zeigt dann die grafische Komposition.
    $featuredRecipes = rescue(fn () => Recipe::query()
        ->hostRecipes() // E18: keine Community-Rezepte auf der Startseite
        ->whereHas('images', fn ($q) => $q->whereNotNull('image_path')->where('image_path', '!=', ''))
        ->with([
            'categories',
            'images' => fn ($q) => $q->whereNotNull('image_path')->where('image_path', '!=', '')->orderBy('id'),
        ])
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->limit(6)
        ->get()
        ->map(fn (Recipe $recipe) => [
            'id' => $recipe->id,
            'name' => $recipe->title,
            'image' => Storage::disk('public')->url($recipe->images->first()->image_path),
            'categories' => $recipe->categories->pluck('name')->values()->all(),
            'duration_minutes' => $recipe->duration_minutes,
        ])
        ->values()
        ->all(), [], report: true);

    return Inertia::render('Landing', [
        'canRegister' => Features::enabled(Features::registration()),
        'featuredRecipes' => $featuredRecipes,
    ]);
})->name('landing');

// E17 / ADR-004: Anmeldung mit Google (Laravel Socialite). Ohne Konfiguration
// leiten beide Routen mit Hinweis auf /login um.
Route::middleware(['guest', 'throttle:google-login'])->group(function () {
    Route::get('/auth/google/redirect', [GoogleLoginController::class, 'redirect'])
        ->name('auth.google.redirect');

    Route::get('/auth/google/callback', [GoogleLoginController::class, 'callback'])
        ->name('auth.google.callback');
});

// Öffentliche Rezept-Ansicht
Route::get('/rezepte', [RecipeController::class, 'index'])
    ->name('recipes.index');

Route::get('/rezept/{recipe}', [RecipeController::class, 'show'])
    ->name('recipes.show');

// E18 / ADR-005: Community-Rezepte (öffentlich: nur freigegebene)
Route::get('/community', [CommunityRecipeController::class, 'index'])
    ->name('community.index');

Route::get('/community/rezept/{recipe}', [CommunityRecipeController::class, 'show'])
    ->name('community.show');

// Meldeweg (Art. 16 DSA): auch für Gäste, mit Rate-Limit
Route::post('/community/rezept/{recipe}/melden', [CommunityRecipeController::class, 'report'])
    ->middleware('throttle:recipe-reports')
    ->name('community.report');

// Öffentliche Zutaten-Suche
Route::get('/zutatensuche', [IngredientController::class, 'index'])
    ->name('IngredientSearch');

// Rechtstexte (Slice 4, F-10): Vorlagen mit Betreiberangaben aus config/legal.php.
// `filled` ist false, solange ein Pflichtwert leer ist oder einen Platzhalter "[" enthält.
$legalProps = static function (): array {
    $legal = (array) config('legal');
    $optional = ['operator_phone', 'content_responsible_name', 'content_responsible_address'];
    $filled = true;

    foreach ($legal as $key => $value) {
        $value = is_string($value) ? trim($value) : $value;
        $legal[$key] = $value === '' ? null : $value;

        if ($legal[$key] === null ? ! in_array($key, $optional, true) : str_contains((string) $legal[$key], '[')) {
            $filled = false;
        }
    }

    return [...$legal, 'filled' => $filled];
};

Route::get('/impressum', fn () => Inertia::render('legal/Impressum', [
    'legal' => $legalProps(),
]))->name('legal.imprint');

Route::get('/datenschutz', fn () => Inertia::render('legal/Datenschutz', [
    'legal' => $legalProps(),
    // Technische Angaben für den Abschnitt "Cookies" (aus config/session.php)
    'session' => [
        'cookie' => (string) config('session.cookie'),
        'lifetime_minutes' => (int) config('session.lifetime'),
        'database' => config('session.driver') === 'database',
    ],
]))->name('legal.privacy');

// Authentifizierte, verifizierte Benutzer (Sammlungen etc.)
// 'verified' wirkt nur bei plantmel.require_email_verification = true (F-08/E2).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/home', function (Request $request) {
        $user = $request->user();

        // E18: Kennzahlen und „Neu“ nur aus dem Rezeptbuch des Hosts
        $totalRecipes = Recipe::hostRecipes()->count();
        $totalCollections = Collection::where('user_id', $user->id)->count();

        $latestRecipes = Recipe::hostRecipes()->with(['categories', 'images'])
            ->orderByDesc('created_at')
            ->limit(4)
            ->get()
            ->map(function (Recipe $recipe) {
                $firstImage = $recipe->images->first();
                $imageUrl = $firstImage?->image_path
                    ? Storage::disk('public')->url($firstImage->image_path)
                    : null;

                return [
                    'id' => $recipe->id,
                    'name' => $recipe->title,
                    'categories' => $recipe->categories->pluck('name')->all(),
                    'image' => $imageUrl,
                ];
            });

        return Inertia::render('Home', [
            'stats' => [
                'totalRecipes' => $totalRecipes,
                'totalCollections' => $totalCollections,
            ],
            'latestRecipes' => $latestRecipes,
        ]);
    })->name('home');

    Route::get('/meineZutaten', function () {
        // F-03: Die Seite braucht die Prop `ingredients`. Gespeicherte
        // "eigene Zutaten" gibt es noch nicht (Feature folgt), daher leer.
        return Inertia::render('MyIngredients', [
            'ingredients' => [],
        ]);
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

    Route::patch('/collections/{collection}', [CollectionController::class, 'update'])
        ->name('collections.update');

    Route::delete('/collections/{collection}', [CollectionController::class, 'delete'])
        ->name('collections.delete');

    // E18: eigene Community-Rezepte (jeder angemeldete Nutzer)
    Route::get('/meine-rezepte', [CommunityRecipeController::class, 'mine'])
        ->name('community.mine');

    Route::get('/community/erstellen', [CommunityRecipeController::class, 'create'])
        ->name('community.create');

    Route::post('/community', [CommunityRecipeController::class, 'store'])
        ->middleware('throttle:community-recipes')
        ->name('community.store');

    Route::get('/community/rezept/{recipe}/bearbeiten', [CommunityRecipeController::class, 'edit'])
        ->name('community.edit');

    Route::post('/community/rezept/{recipe}', [CommunityRecipeController::class, 'update'])
        ->name('community.update');

    Route::delete('/community/rezept/{recipe}', [CommunityRecipeController::class, 'destroy'])
        ->name('community.destroy');

    // TikTok-Import für Nutzer: nur mit eigenem Gemini-Schlüssel (Host: Server-Schlüssel),
    // gleiches Limit wie der Host-Import (pro Nutzer)
    Route::post('/community/import', [RecipeImportController::class, 'store'])
        ->middleware('throttle:recipe-imports')
        ->name('communityImports.store');

    Route::get('/community/import/{recipeImport}', [RecipeImportController::class, 'show'])
        ->name('communityImports.show');
});

// Host-spezifische Aktionen (Rezepte, Kategorien, Zutaten, Alternativen)
// 'verified' wirkt nur bei plantmel.require_email_verification = true (F-08/E2).
Route::middleware(['auth', 'verified', 'is_host'])->group(function () {

    // Rezepte anlegen / bearbeiten / löschen
    Route::get('/rezepte/erstellen', function (Request $request) {
        // E14: nur die Geschmacks-Dimension (0 oder 1 Eintrag)
        $ratingDimensions = TasteRating::dimensionsForForm();
        $categories = Category::orderBy('name')
            ->get(['id', 'name']);
        // E18: nur Katalog-Zutaten (keine offenen Vorschläge)
        $ingredients = Ingredient::approved()->orderBy('name')
            ->get(['id', 'name']);

        // Ergebnis eines TikTok-Imports als Formular-Vorbelegung (eigener, fertiger Import)
        $importedRecipe = RecipeImportController::prefillFor($request, $categories);

        return Inertia::render('recipes/Create', [
            'ratingDimensions' => $ratingDimensions,
            'categories' => $categories,
            'ingredients' => $ingredients,
            'importedRecipe' => $importedRecipe,
        ]);
    })->name('recipes.create');

    // TikTok-Rezeptimport
    Route::post('/rezept-import', [RecipeImportController::class, 'store'])
        ->name('recipeImports.store')->middleware('throttle:recipe-imports');

    Route::get('/rezept-import/{recipeImport}', [RecipeImportController::class, 'show'])
        ->name('recipeImports.show');

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

    // E18: Moderation der Community-Rezepte („Zu prüfen“)
    Route::get('/moderation', [ModerationController::class, 'index'])
        ->name('moderation.index');

    Route::post('/moderation/rezept/{recipe}/freigeben', [ModerationController::class, 'approve'])
        ->name('moderation.approve');

    Route::post('/moderation/rezept/{recipe}/ablehnen', [ModerationController::class, 'reject'])
        ->name('moderation.reject');

    Route::post('/moderation/rezept/{recipe}/ausblenden', [ModerationController::class, 'hide'])
        ->name('moderation.hide');

    Route::post('/moderation/meldung/{report}/erledigt', [ModerationController::class, 'resolveReport'])
        ->name('moderation.reports.resolve');

    Route::patch('/moderation/zutat/{ingredient}', [ModerationController::class, 'renameIngredient'])
        ->name('moderation.ingredients.rename');

    Route::post('/moderation/zutat/{ingredient}/zuordnen', [ModerationController::class, 'mergeIngredient'])
        ->name('moderation.ingredients.merge');

});

require __DIR__.'/settings.php';
