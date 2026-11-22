<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesRecipeContent;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Ingredient;
use App\Models\IngredientAlternative;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Models\RecipeRating;
use App\Services\CommunityRecipes;
use App\Services\TasteRating;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RecipeController extends Controller
{
    use HandlesRecipeContent;

    public function show(Request $request, Recipe $recipe): Response|RedirectResponse
    {
        // E18: Community-Rezepte haben eine eigene Seite. Sichtbare werden
        // dorthin umgeleitet (z. B. Links aus Sammlungen), alle anderen gibt
        // es hier nicht (404, verrät nichts über Entwürfe).
        if ($recipe->isCommunity()) {
            abort_unless($recipe->isVisibleTo($request->user()), 404);

            return redirect()->route('community.show', $recipe);
        }

        // E14: nur die Geschmacks-Bewertung laden, andere Dimensionen bleiben unberührt
        $tasteDimensionId = TasteRating::dimensionId();

        $recipe->load([
            'images',
            'recipeIngredients.ingredient',
            'ratings' => TasteRating::constrain($tasteDimensionId),
            'categories',
            'ingredientAlternatives.alternativeIngredient',
        ]);

        $primaryImage = $recipe->images->first();

        // Alternativzutaten nach Basis-Zutat gruppieren
        $alternativesByIngredient = $recipe->ingredientAlternatives
            ->groupBy('ingredient_id')
            ->map(function ($group) {
                return $group
                    ->map(fn (IngredientAlternative $alt) => $alt->alternativeIngredient?->name)
                    ->filter()
                    ->values()
                    ->all();
            });

        // Ingredients aus RecipeIngredient + Ingredient zusammensetzen
        $ingredients = $recipe->recipeIngredients
            ->map(function ($ri) use ($alternativesByIngredient) {
                return [
                    // aktuell speichern wir die Menge als note (z. B. "200 g")
                    'amount' => $ri->note,
                    'name' => $ri->ingredient?->name,
                    'alternatives' => $alternativesByIngredient->get(
                        $ri->ingredient_id,
                        [],
                    ), // array<string>
                ];
            })
            ->values();

        // Steps & Notes aus instructions ableiten (gemeinsamer Parser, ADR-001).
        // Mehrzeilige Schritte kommen als EIN String mit "\n" zurück.
        [$steps, $notes] = $this->parseInstructions($recipe->instructions);

        $servings = $recipe->servings;

        // E14: einzige Bewertung ist Geschmack (Durchschnitt, eine Nachkommastelle)
        $ratingCount = $recipe->ratings->count();
        $ratingAvg = $ratingCount > 0
            ? round($recipe->ratings->avg('value'), 1)
            : null;

        $collections = $request->user()
            ? Collection::where('user_id', $request->user()->id)
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        return Inertia::render('recipes/Show', [
            'recipe' => [
                'id' => $recipe->id,
                'name' => $recipe->title,
                'description' => $recipe->description,
                'image' => $primaryImage
                    ? Storage::disk('public')->url($primaryImage->image_path)
                    : null,

                'duration_minutes' => $recipe->duration_minutes,
                'categories' => $recipe->categories->pluck('name')->all(),
                'tags' => [],

                'servings' => $servings,

                // Bewertung (E14): nur Geschmack
                'rating' => $ratingAvg,          // Ø Geschmack (z. B. 4.3) oder null
                'ratingCount' => $ratingCount,        // Anzahl Geschmacks-Bewertungen
                'ratingDimensions' => [],                  // bleibt leer (Props-Vertrag, E14)

                // F-15: Feld „Quelle“ entfällt (Entscheidung des Auftraggebers).

                'ingredients' => $ingredients,
                'steps' => $steps,
                'notes' => $notes,

                'createdAt' => $recipe->created_at?->toDateString(),
                'updatedAt' => $recipe->updated_at?->toDateString(),
            ],
            'collections' => $collections,
            'isHost' => (bool) optional($request->user())->is_host,
        ]);
    }

    /** Gate D2 (F-23): Rezepte je Seite in der öffentlichen Liste. */
    public const INDEX_PER_PAGE = 24;

    public function index(Request $request): Response|RedirectResponse
    {
        // F-22: Query-Parameter werden normalisiert statt validiert. Ungültige
        // Formen (z. B. ?search[]=a oder verschachtelte Arrays) werden still
        // ignoriert – die öffentliche Liste antwortet weiter mit 200, statt
        // mit einem TypeError (500) oder einem Redirect abzubrechen.
        $search = $request->query('search');
        $search = is_string($search) ? $search : null;

        // --- Include Ingredients (Array erzwingen, trimmen, leere entfernen) ---
        $includeIngredients = $this->normalizeStringList($request->query('includeIngredients', []));

        // --- Exclude Ingredients ---
        $excludeIngredients = $this->normalizeStringList($request->query('excludeIngredients', []));

        // --- Tags (mehrere) ---
        $tags = $this->normalizeStringList($request->query('tags', []));

        // E14: nur Geschmacks-Bewertungen laden
        $tasteDimensionId = TasteRating::dimensionId();

        // F-23 (Gate D2): keine Zutaten mehr per Eager Loading – die Karten
        // zeigen keine Zutaten (recipes/Index.vue, RecipeDetailCard.vue). Die
        // Zutaten-Filter unten laufen weiter als Unterabfragen (whereHas).
        // E18: nur das Rezeptbuch des Hosts, keine Community-Rezepte
        $query = Recipe::query()
            ->hostRecipes()
            ->with([
                'categories',
                'ratings' => TasteRating::constrain($tasteDimensionId),
                // F-98: Die Karte zeigt nur das erste Bild – je Rezept genau
                // eins laden (Limit je Elternteil, Fensterfunktion).
                'images' => fn ($query) => $query->orderBy('id')->limit(1),
            ]);

        // -------------------------------------------
        // Freitext: Titel ODER irgendeine Zutat
        // -------------------------------------------
        if ($search && trim($search) !== '') {
            $searchTerm = trim($search);
            $like = '%'.$searchTerm.'%';

            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhereHas('recipeIngredients.ingredient', function ($qIng) use ($like) {
                        $qIng->where('name', 'like', $like);
                    });
            });
        }

        // -------------------------------------------
        // Enthaltene Zutaten: Rezept muss JEDE davon haben
        // -------------------------------------------
        if (! empty($includeIngredients)) {
            foreach ($includeIngredients as $ingredientName) {
                $like = '%'.$ingredientName.'%';

                $query->whereHas('recipeIngredients.ingredient', function ($q) use ($like) {
                    $q->where('name', 'like', $like);
                });
            }
        }

        // -------------------------------------------
        // Auszuschließende Zutaten:
        // Rezept darf KEINE der angegebenen Zutaten enthalten
        // -------------------------------------------
        if (! empty($excludeIngredients)) {
            foreach ($excludeIngredients as $ingredientName) {
                $like = '%'.$ingredientName.'%';

                $query->whereDoesntHave('recipeIngredients.ingredient', function ($q) use ($like) {
                    $q->where('name', 'like', $like);
                });
            }
        }

        // -------------------------------------------
        // Tags (Kategorienamen): Rezept muss alle gewählten Tags haben
        // -------------------------------------------
        if (! empty($tags)) {
            foreach ($tags as $tagName) {
                $query->whereHas('categories', function ($q) use ($tagName) {
                    $q->where('name', $tagName);
                });
            }
        }

        // -------------------------------------------
        // F-23 (Gate D2): serverseitige Pagination
        // -------------------------------------------
        // Ungültige Werte (keine positive Ganzzahl, Array, Überlauf) => Seite 1,
        // wie Laravels Standard. Eine Seite hinter der letzten wird auf die
        // letzte umgeleitet (302, Filter bleiben erhalten): Lesezeichen und
        // geteilte Links zeigen nach Löschungen weiter Rezepte statt einer
        // leeren Seite. Die Gesamtzahl wird vorab gezählt, damit für zu hohe
        // Seiten kein riesiger OFFSET an die Datenbank geht.
        $page = filter_var($request->query('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $page = $page === false ? 1 : $page;

        $total = (clone $query)->toBase()->getCountForPagination();
        $lastPage = max(1, (int) ceil($total / self::INDEX_PER_PAGE));

        if ($page > $lastPage) {
            if ($total > 0) {
                return redirect()->to($request->fullUrlWithQuery(['page' => $lastPage]));
            }
            $page = 1;
        }

        // stabile Reihenfolge über Seiten hinweg (bisher implizit Einfügereihenfolge)
        $paginator = $query
            ->orderBy('recipes.id')
            ->paginate(self::INDEX_PER_PAGE, ['recipes.*'], 'page', $page, $total)
            ->withQueryString();

        $recipes = $paginator
            ->getCollection()
            ->map(function (Recipe $recipe) use ($tasteDimensionId) {
                $firstImage = $recipe->images->first();
                $imageUrl = $firstImage?->image_path
                    ? Storage::disk('public')->url($firstImage->image_path)
                    : null;

                return [
                    'id' => $recipe->id,
                    'name' => $recipe->title,

                    // 0–5, ganzzahlig, nur Geschmack (E14)
                    'rating' => TasteRating::roundedForList($recipe->ratings, $tasteDimensionId),

                    // Tags (Kategorienamen)
                    'tags' => $recipe->categories->pluck('name')->all(),

                    // Dauer als fertiger String
                    'time' => $recipe->duration_minutes
                        ? $recipe->duration_minutes.' Min'
                        : '–',

                    'image' => $imageUrl,
                ];
            })
            ->values();

        // Tags-Liste (für Filter oben). Gate D2: beide Tag-Props lazy – sie
        // hängen weder von Seite noch Filter ab, Partial Reloads beim Blättern
        // und Filtern (recipes/Index.vue) fragen sie deshalb nicht erneut ab.
        $allTags = fn () => Category::orderBy('name')->pluck('name')->all();

        // Tag-Statistiken (Anzahl Rezepte pro Kategorie)
        // E18: nur Host-Rezepte zählen
        $tagStats = fn () => Category::withCount(['recipes' => fn ($q) => $q->hostRecipes()])
            ->get()
            ->map(fn (Category $cat) => [
                'key' => $cat->slug ?? $cat->name,
                'label' => $cat->name,
                'count' => $cat->recipes_count,
            ]);

        return Inertia::render('recipes/Index', [
            // Gate D2: nur die Rezepte der aktuellen Seite (Form wie bisher)
            'recipes' => $recipes,
            'pagination' => $this->paginationProps($paginator),
            'tags' => $allTags,
            'tagStats' => $tagStats,
            'filters' => [
                'search' => $search ?? '',
                'includeIngredients' => $includeIngredients,
                'excludeIngredients' => $excludeIngredients,
                'tags' => $tags,
            ],
            'isHost' => (bool) optional($request->user())->is_host,
        ]);
    }

    // Gate D2: tote Methode create() entfernt – keine Route zeigte darauf,
    // `recipes.create` ist eine Closure in routes/web.php.

    public function store(Request $request)
    {
        $tasteDimensionId = TasteRating::dimensionId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'], // TEXT-Spalte (F-22)
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'servings' => ['nullable', 'integer', 'min:1'],
            // F-15: kein Feld „source“ mehr; ein mitgesendetes wird ignoriert.

            // Tags aus dem Frontend (werden auf Categories gemappt)
            'tags' => ['array'],
            'tags.*' => ['string', 'max:255'],

            // Zutaten aus dem Formular
            'ingredients' => ['required', 'array'],
            // max:255 = recipe_ingredients.note bzw. ingredients.name (F-22)
            'ingredients.*.amount' => ['nullable', 'string', 'max:255'],
            'ingredients.*.name' => ['required', 'string', 'max:255'],
            'ingredients.*.alternativeIngredientIds' => ['array'],
            'ingredients.*.alternativeIngredientIds.*' => ['integer', 'exists:ingredients,id'],

            // Zubereitungsschritte
            'steps' => ['required', 'array'],
            'steps.*' => ['required', 'string', 'max:65535'],

            // Notizen (hängen wir unten an instructions an)
            'notes' => ['nullable', 'string', 'max:65535'],

            // Ratings: { [dimensionId]: value }
            'ratings' => ['array', $this->ratingDimensionKeysRule($tasteDimensionId)],
            'ratings.*' => ['nullable', 'integer', 'min:1', 'max:5'],

            // Bild-Upload (optional)
            'image' => ['nullable', 'image', 'max:16384'], // 16MB
        ]);

        // Falls bestimmte Arrays nicht gesetzt sind, auf leere Arrays setzen, um Fehler zu vermeiden
        $validated['tags'] = $validated['tags'] ?? [];
        $validated['ingredients'] = $validated['ingredients'] ?? [];
        $validated['steps'] = $validated['steps'] ?? [];
        $validated['ratings'] = $validated['ratings'] ?? [];
        $validated['notes'] = $validated['notes'] ?? null;

        $recipe = null;
        $storedImagePath = null;

        try {
            DB::transaction(function () use (&$recipe, &$storedImagePath, $request, $validated, $tasteDimensionId) {
                // 1) Recipe selbst anlegen
                $recipe = new Recipe;
                $recipe->title = $validated['name'];
                $recipe->description = $validated['description'] ?? null;
                $recipe->duration_minutes = $validated['duration_minutes'] ?? null;
                $recipe->servings = $validated['servings'] ?? null;

                // instructions aus steps (+ optional notes) zusammenbauen
                $stepsText = $this->buildInstructionsText($validated['steps'], $validated['notes']);
                $recipe->instructions = $stepsText;

                $recipe->save();

                // 2) Kategorien per Tag-Namen verknüpfen (tags -> categories)
                if (! empty($validated['tags'])) {
                    $categoryIds = Category::whereIn('name', $validated['tags'])->pluck('id');
                    if ($categoryIds->isNotEmpty()) {
                        $recipe->categories()->sync($categoryIds);
                    }
                }

                // 3) + 4) Zutaten und Alternativzutaten speichern (gemeinsam mit update(), F-13)
                $this->syncIngredientsAndAlternatives($recipe, $validated['ingredients'], CommunityRecipes::hostIngredientResolver());

                // 5) Geschmacks-Bewertung speichern (E14: einzige Dimension)
                $tasteValue = $this->tasteValueFrom($validated['ratings'], $tasteDimensionId);
                if ($tasteValue !== null) {
                    RecipeRating::create([
                        'recipe_id' => $recipe->id,
                        'dimension_id' => $tasteDimensionId,
                        'value' => $tasteValue,
                    ]);
                }

                // 6) Bild speichern (RecipeImage)
                if ($request->hasFile('image')) {
                    $storedImagePath = $request->file('image')->store('recipes', 'public');

                    RecipeImage::create([
                        'recipe_id' => $recipe->id,
                        'image_path' => $storedImagePath, // im Frontend per Storage::url() auflösen
                    ]);
                }
            });
        } catch (Throwable $e) {
            // F-19: Rollback -> keine verwaiste Bilddatei zurücklassen
            $this->deletePublicImageFiles(array_filter([$storedImagePath]));

            throw $e;
        }

        return redirect()
            ->route('recipes.show', $recipe)
            ->with('success', 'Rezept wurde erfolgreich erstellt.');
    }

    public function edit(Request $request, Recipe $recipe): Response
    {
        // E18: Community-Rezepte nur über ihr eigenes Formular
        abort_if($recipe->isCommunity(), 404);

        // E14: nur die Geschmacks-Bewertung ins Formular
        $tasteDimensionId = TasteRating::dimensionId();

        $recipe->load([
            'images',
            'categories',
            'recipeIngredients.ingredient',
            'ratings' => TasteRating::constrain($tasteDimensionId),
            'ingredientAlternatives.alternativeIngredient',
        ]);

        // Gate C2: aktuelles Bild für Kopf und Vorschau (gleiches Format wie show())
        $primaryImage = $recipe->images->first();

        // Tags aus Kategorien
        $tags = $recipe->categories->pluck('name')->all();

        // Alternativen nach Ingredient gruppieren
        $alternativesByIngredient = $recipe->ingredientAlternatives
            ->groupBy('ingredient_id')
            ->map(fn ($group) => $group->pluck('alternative_ingredient_id')->all());

        // Ingredients für Formular
        $ingredients = $recipe->recipeIngredients
            ->map(function ($ri) use ($alternativesByIngredient) {
                return [
                    'amount' => $ri->note ?? '',
                    'name' => $ri->ingredient?->name ?? '',
                    'alternativeIngredientIds' => $alternativesByIngredient->get($ri->ingredient_id, []),
                ];
            })
            ->values()
            ->all();

        // steps + notes aus instructions ziehen (gemeinsamer Parser mit show(), ADR-001)
        [$steps, $noteLines] = $this->parseInstructions($recipe->instructions);
        // Formular-Vertrag: Notizen als EIN Text (Zeilen mit "\n") oder null
        $notes = $noteLines !== [] ? implode("\n", $noteLines) : null;

        // Ratings als Map dimension_id => value (nur Geschmack, E14)
        $ratings = $recipe->ratings
            ->groupBy('dimension_id')
            ->map(fn ($group) => (int) $group->avg('value'))
            ->all();

        // E14: nur die Geschmacks-Dimension (0 oder 1 Eintrag)
        $ratingDimensions = TasteRating::dimensionsForForm();

        $categories = Category::orderBy('name')
            ->get(['id', 'name']);

        // E18: nur Katalog-Zutaten (keine offenen Vorschläge)
        $allIngredients = Ingredient::approved()->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('recipes/Edit', [
            'ratingDimensions' => $ratingDimensions,
            'categories' => $categories,
            'ingredients' => $allIngredients,
            'recipe' => [
                'id' => $recipe->id,
                'name' => $recipe->title,
                'description' => $recipe->description,
                'image' => $primaryImage
                    ? Storage::disk('public')->url($primaryImage->image_path)
                    : null,
                'duration_minutes' => $recipe->duration_minutes,
                'servings' => $recipe->servings,
                // F-15: Feld „Quelle“ entfällt
                'tags' => $tags,
                'ingredients' => $ingredients,
                'steps' => $steps,
                'notes' => $notes,
                'ratings' => $ratings,
            ],
            'isHost' => (bool) optional($request->user())->is_host,
        ]);
    }

    public function update(Request $request, Recipe $recipe)
    {
        abort_if($recipe->isCommunity(), 404);

        $tasteDimensionId = TasteRating::dimensionId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'], // TEXT-Spalte (F-22)
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'servings' => ['nullable', 'integer', 'min:1'],
            // F-15: kein Feld „source“ mehr; ein mitgesendetes wird ignoriert.

            // Tags aus dem Frontend (werden auf Categories gemappt)
            'tags' => ['array'],
            'tags.*' => ['string', 'max:255'],

            // Zutaten aus dem Formular
            'ingredients' => ['required', 'array'],
            // max:255 = recipe_ingredients.note bzw. ingredients.name (F-22)
            'ingredients.*.amount' => ['nullable', 'string', 'max:255'],
            'ingredients.*.name' => ['required', 'string', 'max:255'],
            'ingredients.*.alternativeIngredientIds' => ['array'],
            'ingredients.*.alternativeIngredientIds.*' => ['integer', 'exists:ingredients,id'],

            // Zubereitungsschritte
            'steps' => ['required', 'array'],
            'steps.*' => ['required', 'string', 'max:65535'],

            // Notizen (hängen wir unten an instructions an)
            'notes' => ['nullable', 'string', 'max:65535'],

            // Ratings: { [dimensionId]: value }
            'ratings' => ['array', $this->ratingDimensionKeysRule($tasteDimensionId)],
            'ratings.*' => ['nullable', 'integer', 'min:1', 'max:5'],

            // Bild-Upload (optional, darf bei Update leer bleiben)
            'image' => ['nullable', 'image', 'max:16384'], // 16MB
        ]);

        // Defaults wie in store()
        $validated['tags'] = $validated['tags'] ?? [];
        $validated['ingredients'] = $validated['ingredients'] ?? [];
        $validated['steps'] = $validated['steps'] ?? [];
        $validated['ratings'] = $validated['ratings'] ?? [];
        $validated['notes'] = $validated['notes'] ?? null;

        // F-19: Dateien erst nach dem Commit löschen bzw. bei Rollback die neue entfernen
        $newImagePath = null;
        $replacedImagePaths = [];

        try {
            DB::transaction(function () use (&$recipe, &$newImagePath, &$replacedImagePaths, $request, $validated, $tasteDimensionId) {
                // 1) Grunddaten aktualisieren
                $recipe->title = $validated['name'];
                $recipe->description = $validated['description'] ?? null;
                $recipe->duration_minutes = $validated['duration_minutes'] ?? null;
                $recipe->servings = $validated['servings'] ?? null;

                // instructions aus steps (+ optional notes) zusammenbauen
                $stepsText = $this->buildInstructionsText($validated['steps'], $validated['notes']);
                $recipe->instructions = $stepsText;

                $recipe->save();

                // 2) Kategorien per Tag-Namen verknüpfen (tags -> categories)
                if (! empty($validated['tags'])) {
                    $categoryIds = Category::whereIn('name', $validated['tags'])->pluck('id');
                    $recipe->categories()->sync($categoryIds);
                } else {
                    // keine Tags übergeben -> Kategorien lösen
                    $recipe->categories()->detach();
                }

                // 3) Zutaten und Alternativen exakt nach Payload ersetzen (F-13)
                $this->syncIngredientsAndAlternatives($recipe, $validated['ingredients'], CommunityRecipes::hostIngredientResolver());

                // 4) Geschmacks-Bewertung ersetzen oder entfernen (E14).
                // Bewertungen anderer Dimensionen bleiben unangetastet.
                // Wie bisher gilt die Payload als Soll-Zustand: fehlt der
                // Geschmacks-Schlüssel oder ist er null, wird sie entfernt.
                if ($tasteDimensionId !== null) {
                    $tasteValue = $this->tasteValueFrom($validated['ratings'], $tasteDimensionId);

                    if ($tasteValue === null) {
                        $recipe->ratings()->where('dimension_id', $tasteDimensionId)->delete();
                    } else {
                        RecipeRating::updateOrCreate(
                            ['recipe_id' => $recipe->id, 'dimension_id' => $tasteDimensionId],
                            ['value' => $tasteValue],
                        );
                    }
                }

                // 5) Bild aktualisieren (falls neues hochgeladen wurde):
                // alte Datensätze löschen, Dateien erst nach dem Commit
                if ($request->hasFile('image')) {
                    $oldImages = $recipe->images()->get();
                    $replacedImagePaths = $oldImages->pluck('image_path')->filter()->all();
                    $oldImages->each->delete();

                    $newImagePath = $request->file('image')->store('recipes', 'public');

                    RecipeImage::create([
                        'recipe_id' => $recipe->id,
                        'image_path' => $newImagePath,
                    ]);
                }
            });
        } catch (Throwable $e) {
            // Rollback: alte Datei bleibt, neu hochgeladene wird entfernt
            $this->deletePublicImageFiles(array_filter([$newImagePath]));

            throw $e;
        }

        $this->deletePublicImageFiles($replacedImagePaths);

        return redirect()
            ->route('recipes.show', $recipe)
            ->with('success', 'Rezept wurde erfolgreich aktualisiert.');
    }

    public function delete(Request $request, Recipe $recipe): RedirectResponse
    {
        // E18: Community-Rezepte löscht community.destroy (inkl. Vorschläge)
        abort_if($recipe->isCommunity(), 404);

        // F-19: Bildpfade merken, Dateien erst nach erfolgreichem Löschen in der DB entfernen
        $imagePaths = $recipe->images()->pluck('image_path')->filter()->all();

        DB::transaction(function () use ($recipe) {
            // Beziehungen zu Sammlungen lösen (Pivot-Tabelle collection_recipes)
            if (method_exists($recipe, 'collections')) {
                $recipe->collections()->detach();
            }

            // Rezept löschen (recipe_images u. a. per Kaskade)
            $recipe->delete();
        });

        $this->deletePublicImageFiles($imagePaths);

        // Zur Übersicht zurück
        return redirect()
            ->route('recipes.index')
            ->with('success', 'Rezept wurde gelöscht.');
    }
}
