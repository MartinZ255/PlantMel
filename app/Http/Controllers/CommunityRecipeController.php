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
use App\Models\RecipeReport;
use App\Models\User;
use App\Services\CommunityRecipes;
use App\Services\TasteRating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * E18 / ADR-005: Community-Rezepte – öffentliche Liste und Detailseite,
 * Einreichen/Bearbeiten/Löschen eigener Rezepte (inkl. eigener
 * Geschmacks-Bewertung wie bei Host-Rezepten, E14) und Melden.
 *
 * Sichtbarkeit und Rechte: App\Policies\RecipePolicy. Nicht sichtbare
 * Rezepte antworten mit 404 (verrät nichts über Entwürfe anderer).
 */
class CommunityRecipeController extends Controller
{
    use HandlesRecipeContent;

    /** Wie die Host-Liste (F-23): 24 Rezepte je Seite. */
    public const INDEX_PER_PAGE = 24;

    public const MESSAGE_SUBMITTED = 'Danke! Dein Rezept ist eingereicht und wird geprüft. Öffentlich erscheint es erst nach der Freigabe.';

    public const MESSAGE_RESUBMITTED = 'Änderungen gespeichert. Dein Rezept wird erneut geprüft und ist bis zur Freigabe nicht öffentlich.';

    public const MESSAGE_DRAFT = 'Entwurf gespeichert. Nur du siehst ihn, bis du ihn zur Prüfung einreichst.';

    // ------------------------------------------------------------------
    // Öffentlich: Liste und Detailseite
    // ------------------------------------------------------------------

    public function index(Request $request): Response|RedirectResponse
    {
        // F-22: Query-Werte normalisieren statt validieren (wie /rezepte)
        $search = $request->query('search');
        $search = is_string($search) ? $search : null;
        $includeIngredients = $this->normalizeStringList($request->query('includeIngredients', []));
        $excludeIngredients = $this->normalizeStringList($request->query('excludeIngredients', []));
        $tags = $this->normalizeStringList($request->query('tags', []));

        // E14: nur Geschmacks-Bewertungen (vom Autor vergeben)
        $tasteDimensionId = TasteRating::dimensionId();

        $query = Recipe::query()
            ->publishedCommunity()
            ->with([
                'categories',
                'author:id,name',
                'ratings' => TasteRating::constrain($tasteDimensionId),
                'images' => fn ($query) => $query->orderBy('id')->limit(1),
            ]);

        if ($search && trim($search) !== '') {
            $like = '%'.trim($search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhereHas('recipeIngredients.ingredient', fn ($qIng) => $qIng->where('name', 'like', $like));
            });
        }

        foreach ($includeIngredients as $ingredientName) {
            $like = '%'.$ingredientName.'%';
            $query->whereHas('recipeIngredients.ingredient', fn ($q) => $q->where('name', 'like', $like));
        }

        foreach ($excludeIngredients as $ingredientName) {
            $like = '%'.$ingredientName.'%';
            $query->whereDoesntHave('recipeIngredients.ingredient', fn ($q) => $q->where('name', 'like', $like));
        }

        foreach ($tags as $tagName) {
            $query->whereHas('categories', fn ($q) => $q->where('name', $tagName));
        }

        $page = $this->requestedPage($request->query('page'));
        $total = (clone $query)->toBase()->getCountForPagination();
        $lastPage = max(1, (int) ceil($total / self::INDEX_PER_PAGE));

        if ($page > $lastPage) {
            if ($total > 0) {
                return redirect()->to($request->fullUrlWithQuery(['page' => $lastPage]));
            }
            $page = 1;
        }

        // neueste Freigaben zuerst, stabil über Seiten hinweg
        $paginator = $query
            ->orderByDesc('recipes.reviewed_at')
            ->orderByDesc('recipes.id')
            ->paginate(self::INDEX_PER_PAGE, ['recipes.*'], 'page', $page, $total)
            ->withQueryString();

        $recipes = $paginator->getCollection()
            ->map(fn (Recipe $recipe) => [
                'id' => $recipe->id,
                'name' => $recipe->title,
                'rating' => TasteRating::roundedForList($recipe->ratings, $tasteDimensionId),
                'tags' => $recipe->categories->pluck('name')->all(),
                'time' => $recipe->duration_minutes ? $recipe->duration_minutes.' Min' : '–',
                'image' => $this->imageUrl($recipe->images->first()),
                'author' => $recipe->author?->name,
            ])
            ->values();

        return Inertia::render('community/Index', [
            'recipes' => $recipes,
            'pagination' => $this->paginationProps($paginator),
            'tags' => fn () => Category::orderBy('name')->pluck('name')->all(),
            'tagStats' => fn () => Category::withCount(['recipes' => fn ($q) => $q->publishedCommunity()])
                ->get()
                ->map(fn (Category $cat) => [
                    'key' => $cat->slug ?? $cat->name,
                    'label' => $cat->name,
                    'count' => $cat->recipes_count,
                ]),
            'filters' => [
                'search' => $search ?? '',
                'includeIngredients' => $includeIngredients,
                'excludeIngredients' => $excludeIngredients,
                'tags' => $tags,
            ],
        ]);
    }

    public function show(Request $request, Recipe $recipe): Response
    {
        $this->ensureCommunity($recipe);
        $user = $request->user();
        abort_unless(Gate::forUser($user)->allows('view', $recipe), 404);

        $tasteDimensionId = TasteRating::dimensionId();

        $recipe->load([
            'images',
            'recipeIngredients.ingredient',
            'ratings' => TasteRating::constrain($tasteDimensionId),
            'categories',
            'ingredientAlternatives.alternativeIngredient',
            'author:id,name',
        ]);

        $canManage = $user !== null && Gate::forUser($user)->allows('update', $recipe);

        $alternativesByIngredient = $recipe->ingredientAlternatives
            ->groupBy('ingredient_id')
            ->map(fn ($group) => $group
                ->map(fn (IngredientAlternative $alt) => $alt->alternativeIngredient?->name)
                ->filter()
                ->values()
                ->all());

        $ingredients = $recipe->recipeIngredients
            ->map(fn ($ri) => [
                'amount' => $ri->note,
                'name' => $ri->ingredient?->name,
                'alternatives' => $alternativesByIngredient->get($ri->ingredient_id, []),
                // nur Autor und Host sehen, welche Zutaten neu vorgeschlagen sind
                'proposed' => $canManage && (bool) $ri->ingredient?->isProposed(),
            ])
            ->values();

        [$steps, $notes] = $this->parseInstructions($recipe->instructions);

        // E14: einzige Bewertung ist Geschmack, vergeben vom Autor (wie beim Host)
        $ratingCount = $recipe->ratings->count();
        $ratingAvg = $ratingCount > 0 ? round($recipe->ratings->avg('value'), 1) : null;

        $primaryImage = $recipe->images->first();

        return Inertia::render('community/Show', [
            'recipe' => [
                'id' => $recipe->id,
                'name' => $recipe->title,
                'description' => $recipe->description,
                'image' => $this->imageUrl($primaryImage),
                'duration_minutes' => $recipe->duration_minutes,
                'servings' => $recipe->servings,
                'categories' => $recipe->categories->pluck('name')->all(),
                'rating' => $ratingAvg,
                'ratingCount' => $ratingCount,
                'ingredients' => $ingredients,
                'steps' => $steps,
                'notes' => $notes,
                'author' => $recipe->author?->name,
                'isOwn' => $recipe->isAuthoredBy($user),
                // Status nur für Autor und Host
                'status' => $canManage ? $recipe->status : null,
                'rejectionReason' => $canManage ? $recipe->rejection_reason : null,
                'createdAt' => $recipe->created_at?->toDateString(),
                'updatedAt' => $recipe->updated_at?->toDateString(),
            ],
            'can' => [
                'edit' => $canManage,
                'delete' => $canManage,
                'moderate' => $user !== null && Gate::forUser($user)->allows('moderate', $recipe),
                'report' => Gate::forUser($user)->allows('report', $recipe),
                'collect' => $recipe->isApproved() || $canManage,
            ],
            'reportReasons' => collect(RecipeReport::REASONS)
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values(),
            'collections' => $user
                ? Collection::where('user_id', $user->id)->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    // ------------------------------------------------------------------
    // Angemeldet: eigene Rezepte
    // ------------------------------------------------------------------

    public function mine(Request $request): Response
    {
        $recipes = Recipe::query()
            ->where('type', Recipe::TYPE_COMMUNITY)
            ->where('user_id', $request->user()->id)
            ->with(['images' => fn ($query) => $query->orderBy('id')->limit(1)])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Recipe $recipe) => [
                'id' => $recipe->id,
                'name' => $recipe->title,
                'status' => $recipe->status,
                'rejectionReason' => $recipe->rejection_reason,
                'image' => $this->imageUrl($recipe->images->first()),
                'updatedAt' => $recipe->updated_at?->toDateString(),
                'submittedAt' => $recipe->submitted_at?->toDateString(),
                'reviewedAt' => $recipe->reviewed_at?->toDateString(),
            ])
            ->values();

        return Inertia::render('community/Mine', [
            'recipes' => $recipes,
        ]);
    }

    public function create(Request $request): Response
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);

        return Inertia::render('community/Create', [
            // E14: nur die Geschmacks-Dimension (0 oder 1 Eintrag)
            'ratingDimensions' => TasteRating::dimensionsForForm(),
            'categories' => $categories,
            'ingredients' => Ingredient::approved()->orderBy('name')->get(['id', 'name']),
            'importedRecipe' => RecipeImportController::prefillFor($request, $categories),
            'importAvailable' => $this->importAvailableFor($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $this->validateRecipe($request);
        $submit = ($validated['intent'] ?? 'submit') !== 'draft';

        $recipe = new Recipe;
        $storedImagePath = null;

        try {
            DB::transaction(function () use (&$storedImagePath, $recipe, $request, $validated, $user, $submit) {
                $recipe->fill([
                    'title' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'duration_minutes' => $validated['duration_minutes'] ?? null,
                    'servings' => $validated['servings'] ?? null,
                    'instructions' => $this->buildInstructionsText($validated['steps'], $validated['notes'] ?? null),
                ]);
                $recipe->forceFill([
                    'type' => Recipe::TYPE_COMMUNITY,
                    'user_id' => $user->id,
                    'status' => $submit ? Recipe::STATUS_PENDING : Recipe::STATUS_DRAFT,
                    'submitted_at' => $submit ? now() : null,
                ])->save();

                $this->syncCategories($recipe, $validated['tags'] ?? []);
                $this->syncIngredientsAndAlternatives(
                    $recipe,
                    $validated['ingredients'],
                    CommunityRecipes::communityIngredientResolver($user),
                );

                // E14: eigene Geschmacks-Bewertung des Autors (optional)
                $this->syncTasteRating($recipe, $validated['ratings'] ?? []);

                if ($request->hasFile('image')) {
                    $storedImagePath = $request->file('image')->store('recipes', 'public');
                    RecipeImage::create(['recipe_id' => $recipe->id, 'image_path' => $storedImagePath]);
                }
            });
        } catch (Throwable $e) {
            // F-19: Rollback -> keine verwaiste Bilddatei zurücklassen
            $this->deletePublicImageFiles(array_filter([$storedImagePath]));

            throw $e;
        }

        return redirect()
            ->route('community.show', $recipe)
            ->with('success', $submit ? self::MESSAGE_SUBMITTED : self::MESSAGE_DRAFT);
    }

    public function edit(Request $request, Recipe $recipe): Response
    {
        $this->ensureCommunity($recipe);
        $this->authorizeManage($request->user(), $recipe);

        $tasteDimensionId = TasteRating::dimensionId();
        $recipe->load([
            'images', 'categories', 'recipeIngredients.ingredient', 'ingredientAlternatives', 'author:id,name',
            'ratings' => TasteRating::constrain($tasteDimensionId),
        ]);

        $alternativesByIngredient = $recipe->ingredientAlternatives
            ->groupBy('ingredient_id')
            ->map(fn ($group) => $group->pluck('alternative_ingredient_id')->all());

        [$steps, $noteLines] = $this->parseInstructions($recipe->instructions);
        $isAuthor = $recipe->isAuthoredBy($request->user());

        return Inertia::render('community/Edit', [
            'ratingDimensions' => TasteRating::dimensionsForForm(),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'ingredients' => Ingredient::approved()->orderBy('name')->get(['id', 'name']),
            'recipe' => [
                'id' => $recipe->id,
                'name' => $recipe->title,
                'description' => $recipe->description,
                'image' => $this->imageUrl($recipe->images->first()),
                'duration_minutes' => $recipe->duration_minutes,
                'servings' => $recipe->servings,
                'tags' => $recipe->categories->pluck('name')->all(),
                'ingredients' => $recipe->recipeIngredients
                    ->map(fn ($ri) => [
                        'amount' => $ri->note ?? '',
                        'name' => $ri->ingredient?->name ?? '',
                        'alternativeIngredientIds' => $alternativesByIngredient->get($ri->ingredient_id, []),
                    ])
                    ->values()
                    ->all(),
                'steps' => $steps,
                'notes' => $noteLines !== [] ? implode("\n", $noteLines) : null,
                // Map dimension_id => Wert (nur Geschmack, E14)
                'ratings' => $recipe->ratings
                    ->groupBy('dimension_id')
                    ->map(fn ($group) => (int) $group->avg('value'))
                    ->all(),
                'status' => $recipe->status,
                'rejectionReason' => $recipe->rejection_reason,
                'author' => $recipe->author?->name,
            ],
            // Autor: Speichern eines freigegebenen Rezepts verbirgt es bis zur erneuten Freigabe.
            // Nur der Autor bewertet; bearbeitet der Host, bleibt die Bewertung unberührt.
            'isAuthor' => $isAuthor,
            'requiresReview' => $isAuthor,
        ]);
    }

    public function update(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->ensureCommunity($recipe);
        $user = $request->user();
        $this->authorizeManage($user, $recipe);

        $validated = $this->validateRecipe($request);
        $isAuthor = $recipe->isAuthoredBy($user);
        $submit = ($validated['intent'] ?? 'submit') !== 'draft';

        $newImagePath = null;
        $replacedImagePaths = [];

        try {
            DB::transaction(function () use (&$newImagePath, &$replacedImagePaths, $recipe, $request, $validated, $isAuthor, $submit) {
                $recipe->fill([
                    'title' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'duration_minutes' => $validated['duration_minutes'] ?? null,
                    'servings' => $validated['servings'] ?? null,
                    'instructions' => $this->buildInstructionsText($validated['steps'], $validated['notes'] ?? null),
                ]);

                // Der Autor reicht nach jeder Änderung erneut ein (auch ein
                // freigegebenes Rezept ist danach bis zur Freigabe verborgen);
                // der Host ändert als Moderator, der Status bleibt.
                if ($isAuthor) {
                    $recipe->forceFill($submit
                        ? ['status' => Recipe::STATUS_PENDING, 'submitted_at' => now(), 'rejection_reason' => null]
                        : ['status' => Recipe::STATUS_DRAFT]);
                }
                $recipe->save();

                $this->syncCategories($recipe, $validated['tags'] ?? []);
                $this->syncIngredientsAndAlternatives(
                    $recipe,
                    $validated['ingredients'],
                    // Vorschläge gehören immer dem Autor, auch wenn der Host bearbeitet
                    CommunityRecipes::communityIngredientResolver($recipe->author ?? $request->user()),
                );

                // E14: Die Bewertung ist die des Autors. Wie im Host-Formular gilt
                // die Payload als Soll-Zustand (fehlt/null = entfernen) – aber nur
                // für den Autor; speichert der Host ein fremdes Rezept, bleibt sie.
                if ($isAuthor) {
                    $this->syncTasteRating($recipe, $validated['ratings'] ?? []);
                }

                if ($request->hasFile('image')) {
                    $oldImages = $recipe->images()->get();
                    $replacedImagePaths = $oldImages->pluck('image_path')->filter()->all();
                    $oldImages->each->delete();

                    $newImagePath = $request->file('image')->store('recipes', 'public');
                    RecipeImage::create(['recipe_id' => $recipe->id, 'image_path' => $newImagePath]);
                }
            });
        } catch (Throwable $e) {
            $this->deletePublicImageFiles(array_filter([$newImagePath]));

            throw $e;
        }

        $this->deletePublicImageFiles($replacedImagePaths);
        CommunityRecipes::sweepUnusedProposals();

        $message = ! $isAuthor
            ? 'Rezept wurde gespeichert.'
            : ($submit ? self::MESSAGE_RESUBMITTED : self::MESSAGE_DRAFT);

        return redirect()->route('community.show', $recipe)->with('success', $message);
    }

    public function destroy(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->ensureCommunity($recipe);
        $user = $request->user();
        $this->authorizeManage($user, $recipe);

        $isAuthor = $recipe->isAuthoredBy($user);
        CommunityRecipes::delete($recipe);

        return redirect()
            ->route($isAuthor ? 'community.mine' : 'moderation.index')
            ->with('success', 'Rezept wurde gelöscht.');
    }

    // ------------------------------------------------------------------
    // Melden
    // ------------------------------------------------------------------

    public function report(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->ensureCommunity($recipe);
        abort_unless(Gate::forUser($request->user())->allows('report', $recipe), 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', Rule::in(array_keys(RecipeReport::REASONS))],
            'message' => [
                Rule::requiredIf(fn () => in_array($request->input('reason'), ['illegal', 'copyright', 'other'], true)),
                'nullable', 'string', 'max:2000',
            ],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'good_faith' => ['accepted'],
        ], [
            'message.required' => 'Bitte beschreibe kurz, was an dem Rezept nicht in Ordnung ist.',
            'good_faith.accepted' => 'Bitte bestätige, dass deine Angaben nach bestem Wissen richtig sind.',
        ]);

        RecipeReport::create([
            'recipe_id' => $recipe->id,
            'user_id' => $request->user()?->id,
            'reason' => $validated['reason'],
            'message' => $validated['message'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
        ]);

        return back()->with('success', 'Danke, deine Meldung ist eingegangen. Wir prüfen das Rezept.');
    }

    // ------------------------------------------------------------------
    // Helfer
    // ------------------------------------------------------------------

    /**
     * Gleiche Regeln wie das Host-Formular (F-22) inkl. Geschmacks-Bewertung
     * (E14); Alternativen nur aus dem Katalog; Kategorien nur vorhandene
     * (unbekannte Namen werden ignoriert, Nutzer legen keine an).
     *
     * @return array<string, mixed>
     */
    private function validateRecipe(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'servings' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'tags' => ['array', 'max:30'],
            'tags.*' => ['string', 'max:255'],
            'ingredients' => ['required', 'array', 'max:100'],
            'ingredients.*.amount' => ['nullable', 'string', 'max:255'],
            'ingredients.*.name' => ['required', 'string', 'max:255'],
            'ingredients.*.alternativeIngredientIds' => ['array', 'max:20'],
            'ingredients.*.alternativeIngredientIds.*' => [
                'integer',
                Rule::exists('ingredients', 'id')->where('status', Ingredient::STATUS_APPROVED),
            ],
            'steps' => ['required', 'array', 'max:100'],
            'steps.*' => ['required', 'string', 'max:65535'],
            'notes' => ['nullable', 'string', 'max:65535'],
            'image' => ['nullable', 'image', 'max:16384'], // 16 MB wie Host-Formular
            'intent' => ['nullable', 'string', Rule::in(['draft', 'submit'])],
            // E14: { [Geschmacks-Dimension]: 1–5 | null }, wie im Host-Formular
            'ratings' => ['array', $this->ratingDimensionKeysRule(TasteRating::dimensionId())],
            'ratings.*' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $validated['tags'] ??= [];
        $validated['steps'] ??= [];
        $validated['ingredients'] ??= [];
        $validated['ratings'] ??= [];

        return $validated;
    }

    /**
     * Geschmacks-Bewertung nach Payload setzen oder entfernen (E14, wie
     * RecipeController::update). Bewertungen anderer Dimensionen bleiben.
     *
     * @param  array<int|string, mixed>  $ratings
     */
    private function syncTasteRating(Recipe $recipe, array $ratings): void
    {
        $tasteDimensionId = TasteRating::dimensionId();
        if ($tasteDimensionId === null) {
            return;
        }

        $value = $this->tasteValueFrom($ratings, $tasteDimensionId);

        if ($value === null) {
            $recipe->ratings()->where('dimension_id', $tasteDimensionId)->delete();

            return;
        }

        RecipeRating::updateOrCreate(
            ['recipe_id' => $recipe->id, 'dimension_id' => $tasteDimensionId],
            ['value' => $value],
        );
    }

    /** @param  array<int, string>  $tags */
    private function syncCategories(Recipe $recipe, array $tags): void
    {
        $recipe->categories()->sync(
            $tags === [] ? [] : Category::whereIn('name', $tags)->pluck('id')
        );
    }

    /** Community-Routen gelten nur für Community-Rezepte. */
    private function ensureCommunity(Recipe $recipe): void
    {
        abort_unless($recipe->isCommunity(), 404);
    }

    /** Nicht sichtbar: 404; sichtbar, aber fremd: 403. */
    private function authorizeManage(User $user, Recipe $recipe): void
    {
        abort_unless($recipe->isVisibleTo($user), 404);
        abort_unless(Gate::forUser($user)->allows('update', $recipe), 403, 'Du kannst nur deine eigenen Rezepte bearbeiten.');
    }

    private function importAvailableFor(User $user): bool
    {
        return $user->is_host
            ? trim((string) config('services.gemini.key')) !== ''
            : $user->hasGeminiKey();
    }

    private function imageUrl(?RecipeImage $image): ?string
    {
        return $image?->image_path ? Storage::disk('public')->url($image->image_path) : null;
    }
}
