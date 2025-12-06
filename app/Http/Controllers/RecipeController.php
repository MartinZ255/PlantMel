<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Collection;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Http\Request;
use App\Models\RatingDimension;
use App\Models\Recipe;
use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\RecipeImage;
use App\Models\RecipeIngredient;
use App\Models\RecipeRating;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;

class RecipeController extends Controller
{

    public function show(Request $request, Recipe $recipe): Response
    {
        $recipe->load([
            'images',
            'recipeIngredients.ingredient',
            'ratings.dimension',
            'categories',
        ]);

        $primaryImage = $recipe->images->first();

        // Ingredients aus RecipeIngredient + Ingredient zusammensetzen
        $ingredients = $recipe->recipeIngredients
            ->map(function ($ri) {
                return [
                    // aktuell speichern wir die Menge als note (z. B. "200 g")
                    'amount' => $ri->note,
                    'name'   => $ri->ingredient?->name,
                ];
            })
            ->values();

        // Steps & Notes aus instructions ableiten
        $rawLines = $recipe->instructions
            ? preg_split('/\r\n|\r|\n/', $recipe->instructions)
            : [];

        $steps   = [];
        $notes   = [];
        $inNotes = false;

        foreach ($rawLines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Beginn des Notizen-Blocks erkennen
            if (preg_match('/^Notizen:?$/i', $line)) {
                $inNotes = true;
                continue;
            }

            // Nummerierung "1. Waschen" -> "Waschen"
            if (preg_match('/^\d+\.\s*(.+)$/', $line, $matches)) {
                $text = $matches[1];
            } else {
                $text = $line;
            }

            if ($inNotes) {
                $notes[] = $text;
            } else {
                $steps[] = $text;
            }
        }

        $servings = $recipe->servings;

        // Rating-Dimensionen aggregieren (pro Dimension Durchschnitt + Anzahl)
        $ratingGroups = $recipe->ratings->groupBy('dimension_id');

        $ratingDimensions = $ratingGroups
            ->map(function ($ratingsForDimension) {
                $dimension = $ratingsForDimension->first()->dimension;

                return [
                    'id'    => $dimension?->id,
                    'name'  => $dimension?->name ?? 'Unbenannte Kategorie',
                    'avg'   => round($ratingsForDimension->avg('value'), 1),
                    'count' => $ratingsForDimension->count(),
                ];
            })
            ->values()
            ->all();

        // Gesamtbewertung (alle Ratings zusammen)
        $ratingCount = $recipe->ratings->count();
        $ratingAvg   = $ratingCount > 0
            ? round($recipe->ratings->avg('value'), 1)
            : null;

        $collections = $request->user()
            ? Collection::where('user_id', $request->user()->id)
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        return Inertia::render('recipes/Show', [
            'recipe' => [
                'id'          => $recipe->id,
                'name'        => $recipe->title,
                'description' => $recipe->description,
                'image'       => $primaryImage
                    ? Storage::disk('public')->url($primaryImage->image_path)
                    : null,

                'duration_minutes' => $recipe->duration_minutes,
                'categories'       => $recipe->categories->pluck('name')->all(),
                'tags'             => [],

                'servings'         => $servings,

                // Bewertung: Gesamt + Dimensionen
                'rating'           => $ratingAvg,          // Gesamtbewertung (z. B. 4.3)
                'ratingCount'      => $ratingCount,        // Anzahl aller Bewertungen
                'ratingDimensions' => $ratingDimensions,   // pro Dimension: id, name, avg, count

                'source'      => null,

                'ingredients' => $ingredients,
                'steps'       => $steps,
                'notes'       => $notes,

                'createdAt'   => $recipe->created_at?->toDateString(),
                'updatedAt'   => $recipe->updated_at?->toDateString(),
            ],
            'collections' => $collections,
        ]);
    }


    public function index(): Response
    {
        $recipes = Recipe::query()
            ->with(['categories', 'ratings', 'images'])
            ->get()
            ->map(function (Recipe $recipe) {
                $avgRating = $recipe->ratings->avg('value');

                $firstImage = $recipe->images->first();
                $imageUrl = $firstImage?->image_path
                    ? Storage::disk('public')->url($firstImage->image_path)
                    : null;

                return [
                    'id'   => $recipe->id,
                    'name' => $recipe->title,

                    // 0–5, ganzzahlig
                    'rating' => $avgRating ? (int) round($avgRating) : 0,

                    // Tags (Kategorienamen)
                    'tags' => $recipe->categories->pluck('name')->all(),

                    // Dauer als fertiger String
                    'time' => $recipe->duration_minutes
                        ? $recipe->duration_minutes . ' Min'
                        : '–',

                    'image' => $imageUrl,
                ];
            });

        // Tags-Liste (für Filter oben)
        $allTags = Category::orderBy('name')->pluck('name')->all();

        // Tag-Statistiken (Anzahl Rezepte pro Kategorie)
        $tagStats = Category::withCount('recipes')
            ->get()
            ->map(fn (Category $cat) => [
                'key'   => $cat->slug ?? $cat->name,
                'label' => $cat->name,
                'count' => $cat->recipes_count,
            ]);

        return Inertia::render('recipes/Index', [
            'recipes'  => $recipes,
            'tags'     => $allTags,
            'tagStats' => $tagStats,
        ]);
    }



    public function create()
    {
        $ratingDimensions = RatingDimension::orderBy('sort_order')
            ->get(['id', 'name', 'description']);

        $categories = Category::orderBy('sort_order')
            ->get(['id', 'name']);

        $ingredients = Ingredient::orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Recipes/Create', [
            'ratingDimensions' => $ratingDimensions,
            'categories' => $categories,
            'ingredients' => $ingredients,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'description'          => ['nullable', 'string'],
            'duration_minutes'     => ['nullable', 'integer', 'min:0'],
            'servings'             => ['nullable', 'integer', 'min:1'],
            'source'               => ['nullable', 'string'],   // dto.

            // Tags aus dem Frontend (werden auf Categories gemappt)
            'tags'                 => ['array'],
            'tags.*'               => ['string'],

            // Zutaten aus dem Formular
            'ingredients'              => ['required', 'array'],
            'ingredients.*.amount'     => ['nullable', 'string'],
            'ingredients.*.name'       => ['required', 'string'],

            // Zubereitungsschritte
            'steps'                => ['required', 'array'],
            'steps.*'              => ['required', 'string'],

            // Notizen (hängen wir unten an instructions an)
            'notes'                => ['nullable', 'string'],

            // Ratings: { [dimensionId]: value }
            'ratings'              => ['array'],
            'ratings.*'            => ['nullable', 'integer', 'min:1', 'max:5'],

            // Bild-Upload (optional)
            'image'                => ['nullable', 'image', 'max:16384'], // 16MB
        ]);

        // Falls bestimmte Arrays nicht gesetzt sind, auf leere Arrays setzen, um Fehler zu vermeiden
        $validated['tags']        = $validated['tags']        ?? [];
        $validated['ingredients'] = $validated['ingredients'] ?? [];
        $validated['steps']       = $validated['steps']       ?? [];
        $validated['ratings']     = $validated['ratings']     ?? [];
        $validated['notes']       = $validated['notes']       ?? null;

        $recipe = null;

        DB::transaction(function () use (&$recipe, $request, $validated) {
            // 1) Recipe selbst anlegen
            $recipe           = new Recipe();
            $recipe->title    = $validated['name'];
            $recipe->description = $validated['description'] ?? null;
            $recipe->duration_minutes = $validated['duration_minutes'] ?? null;
            $recipe->servings        = $validated['servings'] ?? null;

            // instructions aus steps (+ optional notes) zusammenbauen
            $stepsText = $this->buildInstructionsText($validated['steps'], $validated['notes']);
            $recipe->instructions = $stepsText;


            $recipe->save();

            // 2) Kategorien per Tag-Namen verknüpfen (tags -> categories)
            if (!empty($validated['tags'])) {
                $categoryIds = Category::whereIn('name', $validated['tags'])->pluck('id');
                if ($categoryIds->isNotEmpty()) {
                    $recipe->categories()->sync($categoryIds);
                }
            }

            // 3) Zutaten speichern (RecipeIngredient + Ingredient)
            foreach ($validated['ingredients'] as $ingredientRow) {
                $name = trim($ingredientRow['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                // Ingredient anhand des Namens finden/erstellen
                $ingredient = Ingredient::firstOrCreate(['name' => $name]);

                // Menge vorerst als Freitext in note ablegen (z. B. "200 g")
                $amountText = $ingredientRow['amount'] ?? null;

                RecipeIngredient::create([
                    'recipe_id'     => $recipe->id,
                    'ingredient_id' => $ingredient->id,
                    'quantity'      => null,        // optional später aus amountText parsen
                    'unit'          => null,
                    'note'          => $amountText,
                ]);
            }

            // 4) Ratings speichern
            foreach ($validated['ratings'] as $dimensionId => $value) {
                if ($value === null) {
                    continue;
                }

                RecipeRating::create([
                    'recipe_id'    => $recipe->id,
                    'dimension_id' => (int) $dimensionId,
                    'value'        => (int) $value,
                ]);
            }

            // 5) Bild speichern (RecipeImage)
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('recipes', 'public');

                RecipeImage::create([
                    'recipe_id'  => $recipe->id,
                    'image_path' => $path, // im Frontend per Storage::url() auflösen
                ]);
            }
        });

        return redirect()
            ->route('recipes.show', $recipe)
            ->with('success', 'Rezept wurde erfolgreich erstellt.');
    }

    /**
     * Baut einen Anzeigetext für instructions aus den einzelnen Steps
     * und optionalen Notizen.
     */
    /**
     * Baut einen Anzeigetext für instructions aus den einzelnen Steps
     * und optionalen Notizen.
     */
    protected function buildInstructionsText(array $steps, ?string $notes): string
    {
        $lines = [];

        // Nummerierte Schritte
        foreach ($steps as $index => $step) {
            $number = $index + 1;
            $lines[] = $number . '. ' . trim($step);
        }

        // Optional: Notizen anhängen
        if ($notes !== null && trim($notes) !== '') {
            $lines[] = 'Notizen:';

            foreach (preg_split('/\r\n|\r|\n/', $notes) as $noteLine) {
                $noteLine = trim($noteLine);
                if ($noteLine === '') {
                    continue;
                }
                $lines[] = $noteLine;
            }
        }

        return implode("\n", $lines);
    }

}
