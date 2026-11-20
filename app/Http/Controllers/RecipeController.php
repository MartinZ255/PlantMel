<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Collection;
use App\Models\IngredientAlternative;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Recipe;
use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\RecipeImage;
use App\Models\RecipeIngredient;
use App\Models\RecipeRating;
use App\Services\TasteRating;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;
use Closure;
use Throwable;

class RecipeController extends Controller
{

    public function show(Request $request, Recipe $recipe): Response
    {
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
                    'name'   => $ri->ingredient?->name,
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

                // Bewertung (E14): nur Geschmack
                'rating'           => $ratingAvg,          // Ø Geschmack (z. B. 4.3) oder null
                'ratingCount'      => $ratingCount,        // Anzahl Geschmacks-Bewertungen
                'ratingDimensions' => [],                  // bleibt leer (Props-Vertrag, E14)

                // F-15: Feld „Quelle“ entfällt (Entscheidung des Auftraggebers).

                'ingredients' => $ingredients,
                'steps'       => $steps,
                'notes'       => $notes,

                'createdAt'   => $recipe->created_at?->toDateString(),
                'updatedAt'   => $recipe->updated_at?->toDateString(),
            ],
            'collections' => $collections,
            'isHost'      => (bool) optional($request->user())->is_host,
        ]);
    }


    public function index(Request $request): Response
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

        $query = Recipe::query()
            ->with([
                'categories',
                'ratings' => TasteRating::constrain($tasteDimensionId),
                'images',
                'recipeIngredients.ingredient',
            ]);

        // -------------------------------------------
        // Freitext: Titel ODER irgendeine Zutat
        // -------------------------------------------
        if ($search && trim($search) !== '') {
            $searchTerm = trim($search);
            $like = '%' . $searchTerm . '%';

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
        if (!empty($includeIngredients)) {
            foreach ($includeIngredients as $ingredientName) {
                $like = '%' . $ingredientName . '%';

                $query->whereHas('recipeIngredients.ingredient', function ($q) use ($like) {
                    $q->where('name', 'like', $like);
                });
            }
        }

        // -------------------------------------------
        // Auszuschließende Zutaten:
        // Rezept darf KEINE der angegebenen Zutaten enthalten
        // -------------------------------------------
        if (!empty($excludeIngredients)) {
            foreach ($excludeIngredients as $ingredientName) {
                $like = '%' . $ingredientName . '%';

                $query->whereDoesntHave('recipeIngredients.ingredient', function ($q) use ($like) {
                    $q->where('name', 'like', $like);
                });
            }
        }

        // -------------------------------------------
        // Tags (Kategorienamen): Rezept muss alle gewählten Tags haben
        // -------------------------------------------
        if (!empty($tags)) {
            foreach ($tags as $tagName) {
                $query->whereHas('categories', function ($q) use ($tagName) {
                    $q->where('name', $tagName);
                });
            }
        }

        $recipes = $query
            ->get()
            ->map(function (Recipe $recipe) use ($tasteDimensionId) {
                $firstImage = $recipe->images->first();
                $imageUrl = $firstImage?->image_path
                    ? Storage::disk('public')->url($firstImage->image_path)
                    : null;

                return [
                    'id'   => $recipe->id,
                    'name' => $recipe->title,

                    // 0–5, ganzzahlig, nur Geschmack (E14)
                    'rating' => TasteRating::roundedForList($recipe->ratings, $tasteDimensionId),

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
            'filters'  => [
                'search'             => $search ?? '',
                'includeIngredients' => $includeIngredients,
                'excludeIngredients' => $excludeIngredients,
                'tags'               => $tags,
            ],
            'isHost'   => (bool) optional($request->user())->is_host,
        ]);
    }

    /**
     * Macht aus einem Query-Wert eine flache Liste getrimmter, nicht leerer
     * Strings. Nicht-String-Elemente (z. B. verschachtelte Arrays) werden
     * verworfen (F-22).
     *
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            $value = [$value];
        }

        $strings = array_filter($value, 'is_string');

        return array_values(array_filter(array_map('trim', $strings)));
    }

    /**
     * Regel für `ratings` (F-22, verschärft durch E14): Erlaubt ist nur der
     * Schlüssel der Geschmacks-Dimension. Jeder andere Schlüssel – auch eine
     * existierende andere Dimension – ist ein Validierungsfehler (422).
     * Fehlt die Geschmacks-Dimension, ist nur eine leere Map erlaubt.
     */
    private function ratingDimensionKeysRule(?int $tasteDimensionId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($tasteDimensionId): void {
            if (! is_array($value) || $value === []) {
                return;
            }

            foreach (array_keys($value) as $key) {
                // exakter Vergleich: „05“ o. Ä. wäre sonst gültig, beim Speichern aber nicht auffindbar
                if ($tasteDimensionId === null || (string) $key !== (string) $tasteDimensionId) {
                    $fail('Bewertet werden kann nur der Geschmack.');

                    return;
                }
            }
        };
    }

    /**
     * Liest den Geschmacks-Wert (1–5) aus den validierten Ratings oder null.
     *
     * @param  array<int|string, mixed>  $ratings
     */
    private function tasteValueFrom(array $ratings, ?int $tasteDimensionId): ?int
    {
        if ($tasteDimensionId === null) {
            return null;
        }

        $value = $ratings[$tasteDimensionId] ?? null;

        return $value === null ? null : (int) $value;
    }

    /**
     * Hinweis: Keine Route zeigt auf diese Methode – `recipes.create` ist eine
     * Closure in routes/web.php. Sie wird nur konsistent gehalten (F-12).
     */
    public function create()
    {
        // E14: nur die Geschmacks-Dimension (0 oder 1 Eintrag)
        $ratingDimensions = TasteRating::dimensionsForForm();

        $categories = Category::orderBy('name')
            ->get(['id', 'name']);

        $ingredients = Ingredient::orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('recipes/Create', [
            'ratingDimensions' => $ratingDimensions,
            'categories' => $categories,
            'ingredients' => $ingredients,
        ]);
    }

    public function store(Request $request)
    {
        $tasteDimensionId = TasteRating::dimensionId();

        $validated = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'description'          => ['nullable', 'string', 'max:65535'], // TEXT-Spalte (F-22)
            'duration_minutes'     => ['nullable', 'integer', 'min:0'],
            'servings'             => ['nullable', 'integer', 'min:1'],
            // F-15: kein Feld „source“ mehr; ein mitgesendetes wird ignoriert.

            // Tags aus dem Frontend (werden auf Categories gemappt)
            'tags'                 => ['array'],
            'tags.*'               => ['string', 'max:255'],

            // Zutaten aus dem Formular
            'ingredients'                          => ['required', 'array'],
            // max:255 = recipe_ingredients.note bzw. ingredients.name (F-22)
            'ingredients.*.amount'                 => ['nullable', 'string', 'max:255'],
            'ingredients.*.name'                   => ['required', 'string', 'max:255'],
            'ingredients.*.alternativeIngredientIds'   => ['array'],
            'ingredients.*.alternativeIngredientIds.*' => ['integer', 'exists:ingredients,id'],


            // Zubereitungsschritte
            'steps'                => ['required', 'array'],
            'steps.*'              => ['required', 'string', 'max:65535'],

            // Notizen (hängen wir unten an instructions an)
            'notes'                => ['nullable', 'string', 'max:65535'],

            // Ratings: { [dimensionId]: value }
            'ratings'              => ['array', $this->ratingDimensionKeysRule($tasteDimensionId)],
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
        $storedImagePath = null;

        try {
            DB::transaction(function () use (&$recipe, &$storedImagePath, $request, $validated, $tasteDimensionId) {
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

                // 3) + 4) Zutaten und Alternativzutaten speichern (gemeinsam mit update(), F-13)
                $this->syncIngredientsAndAlternatives($recipe, $validated['ingredients']);

                // 5) Geschmacks-Bewertung speichern (E14: einzige Dimension)
                $tasteValue = $this->tasteValueFrom($validated['ratings'], $tasteDimensionId);
                if ($tasteValue !== null) {
                    RecipeRating::create([
                        'recipe_id'    => $recipe->id,
                        'dimension_id' => $tasteDimensionId,
                        'value'        => $tasteValue,
                    ]);
                }

                // 6) Bild speichern (RecipeImage)
                if ($request->hasFile('image')) {
                    $storedImagePath = $request->file('image')->store('recipes', 'public');

                    RecipeImage::create([
                        'recipe_id'  => $recipe->id,
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

    /**
     * Speichert die Zutaten eines Rezepts samt Alternativzutaten exakt nach
     * Payload (F-13). Wird von store() und update() innerhalb der jeweiligen
     * Transaktion aufgerufen: Vorhandene Zeilen werden ersetzt, Alternativen
     * von Zutaten, die nicht mehr im Rezept stehen, verschwinden.
     *
     * Ein fehlender Schlüssel `alternativeIngredientIds` bedeutet „keine
     * Alternativen“ (FormData überträgt leere Arrays nicht). Doppelte IDs und
     * die Zutat als ihre eigene Alternative werden still verworfen.
     *
     * @param  array<int, array{name?: string, amount?: string|null, alternativeIngredientIds?: array<int, int|string>}>  $rows
     */
    private function syncIngredientsAndAlternatives(Recipe $recipe, array $rows): void
    {
        $recipe->ingredientAlternatives()->delete();
        $recipe->recipeIngredients()->delete();

        /** @var array<int, array<int, true>> $savedAlternatives ingredient_id => [alternative_id => true] */
        $savedAlternatives = [];

        foreach ($rows as $ingredientRow) {
            $name = trim($ingredientRow['name'] ?? '');
            if ($name === '') {
                continue;
            }

            // Ingredient anhand des Namens finden/erstellen
            $ingredient = Ingredient::firstOrCreate(['name' => $name]);

            // Menge vorerst als Freitext in note ablegen (z. B. "200 g")
            RecipeIngredient::create([
                'recipe_id' => $recipe->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => null,
                'unit' => null,
                'note' => $ingredientRow['amount'] ?? null,
            ]);

            foreach ($ingredientRow['alternativeIngredientIds'] ?? [] as $alternativeId) {
                $alternativeId = (int) $alternativeId;

                if ($alternativeId === $ingredient->id || isset($savedAlternatives[$ingredient->id][$alternativeId])) {
                    continue;
                }

                IngredientAlternative::create([
                    'recipe_id' => $recipe->id,
                    'ingredient_id' => $ingredient->id,
                    'alternative_ingredient_id' => $alternativeId,
                    'note' => null,
                ]);

                $savedAlternatives[$ingredient->id][$alternativeId] = true;
            }
        }
    }

    /**
     * Entfernt Bilddateien vom public-Disk. Wird erst NACH dem Commit (bzw.
     * nach einem Rollback für neu hochgeladene Dateien) aufgerufen (F-19).
     * Pfade, die noch von einem anderen Datensatz referenziert werden, bleiben
     * erhalten. Fehler werden geloggt und lassen den Request nicht scheitern.
     *
     * @param  array<int, string>  $paths
     */
    private function deletePublicImageFiles(array $paths): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            try {
                if (RecipeImage::where('image_path', $path)->exists()) {
                    continue;
                }

                if (! Storage::disk('public')->delete($path)) {
                    Log::warning('Rezeptbild konnte nicht gelöscht werden.', ['path' => $path]);
                }
            } catch (Throwable $e) {
                Log::warning('Rezeptbild konnte nicht gelöscht werden.', [
                    'path' => $path,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Einrückung, mit der jede weitere Zeile eines mehrzeiligen Schritts
     * gespeichert wird (ADR-001). Vier Leerzeichen: Der bisherige Schreiber
     * hat jede Zeile getrimmt, Altbestand beginnt deshalb nie mit Leerzeichen.
     */
    private const STEP_CONTINUATION_INDENT = '    ';

    /**
     * Abschnittskopf der Notizen – gilt nur uneingerückt am Zeilenanfang.
     */
    private const NOTES_HEADER = 'Notizen:';

    /**
     * Schreibt Schritte und Notizen in das Textformat von `recipes.instructions`
     * (ADR-001):
     *
     *   1. Erste Zeile von Schritt 1
     *       Fortsetzungszeile von Schritt 1   (STEP_CONTINUATION_INDENT + Text)
     *   2. Schritt 2
     *   Notizen:
     *   Notizzeile (getrimmt, Leerzeilen entfallen)
     *
     * Gegenstück: parseInstructions().
     *
     * @param  array<int, string>  $steps
     */
    private function buildInstructionsText(array $steps, ?string $notes): string
    {
        $lines = [];

        // Nummerierte Schritte; weitere Zeilen eines Schritts eingerückt
        foreach (array_values($steps) as $index => $step) {
            $stepLines = preg_split('/\r\n|\r|\n/', trim((string) $step));

            $lines[] = ($index + 1).'. '.rtrim((string) array_shift($stepLines));

            foreach ($stepLines as $continuation) {
                $lines[] = self::STEP_CONTINUATION_INDENT.rtrim($continuation);
            }
        }

        // Optional: Notizen anhängen
        if ($notes !== null && trim($notes) !== '') {
            $lines[] = self::NOTES_HEADER;

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

    public function edit(Request $request, Recipe $recipe): Response
    {
        // E14: nur die Geschmacks-Bewertung ins Formular
        $tasteDimensionId = TasteRating::dimensionId();

        $recipe->load([
            'categories',
            'recipeIngredients.ingredient',
            'ratings' => TasteRating::constrain($tasteDimensionId),
            'ingredientAlternatives.alternativeIngredient',
        ]);

        // Tags aus Kategorien
        $tags = $recipe->categories->pluck('name')->all();

        // Alternativen nach Ingredient gruppieren
        $alternativesByIngredient = $recipe->ingredientAlternatives
            ->groupBy('ingredient_id')
            ->map(fn ($group) => $group->pluck('alternative_ingredient_id')->all());

        // Ingredients für Formular
        $ingredients = $recipe->recipeIngredients
            ->map(function ($ri) use ($alternativesByIngredient){
                return [
                    'amount' => $ri->note ?? '',
                    'name'   => $ri->ingredient?->name ?? '',
                    'alternativeIngredientIds'=> $alternativesByIngredient->get($ri->ingredient_id, []),
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

        $allIngredients = Ingredient::orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('recipes/Edit', [
            'ratingDimensions' => $ratingDimensions,
            'categories'       => $categories,
            'ingredients'      => $allIngredients,
            'recipe'           => [
                'id'               => $recipe->id,
                'name'             => $recipe->title,
                'description'      => $recipe->description,
                'duration_minutes' => $recipe->duration_minutes,
                'servings'         => $recipe->servings,
                // F-15: Feld „Quelle“ entfällt
                'tags'             => $tags,
                'ingredients'      => $ingredients,
                'steps'            => $steps,
                'notes'            => $notes,
                'ratings'          => $ratings,
            ],
            'isHost'           => (bool) optional($request->user())->is_host,
        ]);
    }

    /**
     * Liest `recipes.instructions` in Schritte und Notizzeilen (ADR-001).
     * Einziger Leser für show() und edit(); Gegenstück zu buildInstructionsText().
     *
     * - Zeile mit STEP_CONTINUATION_INDENT: Fortsetzung des vorherigen Schritts
     *   (Einrückung wird entfernt, Rest bleibt inkl. führender Leerzeichen).
     * - Uneingerückte Zeile „Notizen:“ / „Notizen“ (Groß-/Kleinschreibung egal):
     *   Beginn der Notizen; alle weiteren Zeilen sind Notizzeilen.
     * - Sonst (auch Altbestand): getrimmt, Leerzeilen entfallen, führende
     *   Nummer „1. “ wird entfernt, jede Zeile ist ein eigener Schritt.
     * - Notizzeilen: getrimmt, Leerzeilen entfallen, Nummern bleiben erhalten.
     *
     * @return array{0: list<string>, 1: list<string>} [Schritte, Notizzeilen]
     */
    private function parseInstructions(?string $instructions): array
    {
        if ($instructions === null || $instructions === '') {
            return [[], []];
        }

        $steps = [];
        $notes = [];
        $inNotes = false;
        $indentLength = strlen(self::STEP_CONTINUATION_INDENT);

        foreach (preg_split('/\r\n|\r|\n/', $instructions) as $rawLine) {
            if ($inNotes) {
                $line = trim($rawLine);
                if ($line !== '') {
                    $notes[] = $line;
                }

                continue;
            }

            if ($steps !== [] && str_starts_with($rawLine, self::STEP_CONTINUATION_INDENT)) {
                $steps[array_key_last($steps)] .= "\n".rtrim(substr($rawLine, $indentLength));

                continue;
            }

            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            // Abschnittskopf nur uneingerückt am Zeilenanfang
            if (preg_match('/^'.rtrim(self::NOTES_HEADER, ':').':?\s*$/i', $rawLine)) {
                $inNotes = true;

                continue;
            }

            // Nummerierung "1. Waschen" -> "Waschen"
            $steps[] = preg_match('/^\d+\.\s*(.+)$/', $line, $matches) ? $matches[1] : $line;
        }

        return [$steps, $notes];
    }

    public function update(Request $request, Recipe $recipe)
    {
        $tasteDimensionId = TasteRating::dimensionId();

        $validated = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'description'          => ['nullable', 'string', 'max:65535'], // TEXT-Spalte (F-22)
            'duration_minutes'     => ['nullable', 'integer', 'min:0'],
            'servings'             => ['nullable', 'integer', 'min:1'],
            // F-15: kein Feld „source“ mehr; ein mitgesendetes wird ignoriert.

            // Tags aus dem Frontend (werden auf Categories gemappt)
            'tags'                 => ['array'],
            'tags.*'               => ['string', 'max:255'],

            // Zutaten aus dem Formular
            'ingredients'                          => ['required', 'array'],
            // max:255 = recipe_ingredients.note bzw. ingredients.name (F-22)
            'ingredients.*.amount'                 => ['nullable', 'string', 'max:255'],
            'ingredients.*.name'                   => ['required', 'string', 'max:255'],
            'ingredients.*.alternativeIngredientIds'   => ['array'],
            'ingredients.*.alternativeIngredientIds.*' => ['integer', 'exists:ingredients,id'],


            // Zubereitungsschritte
            'steps'                => ['required', 'array'],
            'steps.*'              => ['required', 'string', 'max:65535'],

            // Notizen (hängen wir unten an instructions an)
            'notes'                => ['nullable', 'string', 'max:65535'],

            // Ratings: { [dimensionId]: value }
            'ratings'              => ['array', $this->ratingDimensionKeysRule($tasteDimensionId)],
            'ratings.*'            => ['nullable', 'integer', 'min:1', 'max:5'],

            // Bild-Upload (optional, darf bei Update leer bleiben)
            'image'                => ['nullable', 'image', 'max:16384'], // 16MB
        ]);

        // Defaults wie in store()
        $validated['tags']        = $validated['tags']        ?? [];
        $validated['ingredients'] = $validated['ingredients'] ?? [];
        $validated['steps']       = $validated['steps']       ?? [];
        $validated['ratings']     = $validated['ratings']     ?? [];
        $validated['notes']       = $validated['notes']       ?? null;

        // F-19: Dateien erst nach dem Commit löschen bzw. bei Rollback die neue entfernen
        $newImagePath = null;
        $replacedImagePaths = [];

        try {
            DB::transaction(function () use (&$recipe, &$newImagePath, &$replacedImagePaths, $request, $validated, $tasteDimensionId) {
                // 1) Grunddaten aktualisieren
                $recipe->title            = $validated['name'];
                $recipe->description      = $validated['description'] ?? null;
                $recipe->duration_minutes = $validated['duration_minutes'] ?? null;
                $recipe->servings         = $validated['servings'] ?? null;

                // instructions aus steps (+ optional notes) zusammenbauen
                $stepsText = $this->buildInstructionsText($validated['steps'], $validated['notes']);
                $recipe->instructions = $stepsText;

                $recipe->save();

                // 2) Kategorien per Tag-Namen verknüpfen (tags -> categories)
                if (!empty($validated['tags'])) {
                    $categoryIds = Category::whereIn('name', $validated['tags'])->pluck('id');
                    $recipe->categories()->sync($categoryIds);
                } else {
                    // keine Tags übergeben -> Kategorien lösen
                    $recipe->categories()->detach();
                }

                // 3) Zutaten und Alternativen exakt nach Payload ersetzen (F-13)
                $this->syncIngredientsAndAlternatives($recipe, $validated['ingredients']);

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
                        'recipe_id'  => $recipe->id,
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
