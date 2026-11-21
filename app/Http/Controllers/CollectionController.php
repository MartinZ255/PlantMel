<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Recipe;
use App\Services\TasteRating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    /** Höchstzahl der Rezeptbilder je Sammlung für die Collage der Übersicht */
    private const COLLAGE_IMAGES = 4;

    public function index(Request $request)
    {
        $collections = Collection::query()
            ->where('user_id', $request->user()->id)
            // Collage: nur Rezepte mit Bild, höchstens 4 je Sammlung (Limit je
            // Sammlung per Eager Loading, keine Abfrage pro Sammlung/Rezept).
            // E18: nur Rezepte, die der Nutzer sehen darf (z. B. kein
            // fremdes Community-Rezept, das wieder geprüft wird)
            ->withCount(['recipes' => fn ($query) => $query->visibleTo($request->user())])
            ->with([
                'recipes' => fn ($query) => $query
                    ->visibleTo($request->user())
                    ->whereHas('images')
                    ->with(['images' => fn ($images) => $images->orderBy('id')])
                    ->orderByDesc('recipes.id')
                    ->limit(self::COLLAGE_IMAGES),
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Collection $collection) {
                $images = $collection->recipes
                    ->map(fn (Recipe $recipe) => $recipe->images->first()?->image_path)
                    ->filter()
                    // gleiche Disk wie RecipeController/Home (Rezeptbilder liegen auf "public")
                    ->map(fn (string $path) => Storage::disk('public')->url($path))
                    ->take(self::COLLAGE_IMAGES)
                    ->values()
                    ->all();

                return [
                    'id' => $collection->id,
                    'name' => $collection->name,
                    'description' => $collection->description,
                    'recipeCount' => $collection->recipes_count,
                    'images' => $images,
                ];
            });

        return Inertia::render('collections/Index', [
            'collections' => $collections,
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('collections/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $collection = Collection::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('collections.index')
            ->with('success', 'Sammlung wurde erstellt.');
    }

    public function show(Request $request, Collection $collection): Response
    {
        if ($collection->user_id !== $request->user()->id) {
            abort(403);
        }

        // E14: nur Geschmacks-Bewertungen laden und einrechnen
        $tasteDimensionId = TasteRating::dimensionId();

        $collection->load([
            // E18: nur sichtbare Rezepte (Community-Rezepte in Prüfung bleiben verborgen)
            'recipes' => fn ($query) => $query->visibleTo($request->user()),
            'recipes.categories',
            'recipes.ratings' => TasteRating::constrain($tasteDimensionId),
            'recipes.images',
        ]);

        $recipes = $collection->recipes->map(function ($recipe) use ($tasteDimensionId) {
            $firstImage = $recipe->images->first();
            $imageUrl = $firstImage?->image_path
                ? Storage::url($firstImage->image_path)
                : null;

            return [
                'id' => $recipe->id,
                'name' => $recipe->title,
                'rating' => TasteRating::roundedForList($recipe->ratings, $tasteDimensionId),
                'tags' => $recipe->categories->pluck('name')->all(),
                'time' => $recipe->duration_minutes
                    ? $recipe->duration_minutes.' Min'
                    : '–',
                'image' => $imageUrl,
            ];
        });

        return Inertia::render('collections/Show', [
            'collection' => [
                'id' => $collection->id,
                'name' => $collection->name,
                'description' => $collection->description,
                'updatedAt' => $collection->updated_at?->diffForHumans(),
            ],
            'recipes' => $recipes,
        ]);
    }

    public function update(Request $request, Collection $collection): RedirectResponse
    {
        // Gleiche Besitzprüfung wie show/delete: fremde Sammlung -> 403
        // (vor der Validierung, damit nichts über fremde Daten verrät).
        if ((int) $collection->user_id !== (int) $request->user()->id) {
            abort(403, 'Diese Sammlung gehört dir nicht.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $collection->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('collections.show', $collection)
            ->with('success', 'Sammlung wurde gespeichert.');
    }

    public function addRecipe(Request $request, Collection $collection)
    {
        if ($collection->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'recipe_id' => ['required', 'exists:recipes,id'],
        ]);

        // E18: nur Rezepte, die der Nutzer sehen darf (keine fremden Entwürfe)
        $recipe = Recipe::findOrFail($data['recipe_id']);
        abort_unless($recipe->isVisibleTo($request->user()), 404);

        $collection->recipes()->syncWithoutDetaching([$data['recipe_id']]);

        return back()->with('success', 'Rezept wurde zur Sammlung hinzugefügt.');
    }

    public function removeRecipe(Collection $collection, Recipe $recipe, Request $request)
    {
        // Sicherheitscheck: gehört die Collection dem aktuellen User?
        if ($collection->user_id !== $request->user()->id) {
            abort(403);
        }
        // Beziehung lösen (Pivot-Tabelle z. B. collection_recipes)
        $collection->recipes()->detach($recipe->id);

        // Zurück zur Collection-Seite, Inertia kümmert sich um das Refreshen der Liste
        return redirect()
            ->route('collections.show', $collection)
            ->with('success', 'Rezept wurde aus der Sammlung entfernt.');
    }

    public function delete(Request $request, Collection $collection): RedirectResponse
    {
        // Sicherheit: gehört die Sammlung dem aktuellen User?
        if ((int) $collection->user_id !== (int) $request->user()->id) {
            abort(403, 'Diese Sammlung gehört dir nicht.');
        }

        // Beziehungen zu Rezepten lösen (Pivot-Tabelle collection_recipes)
        if (method_exists($collection, 'recipes')) {
            $collection->recipes()->detach();
        }

        // Sammlung löschen
        $collection->delete();

        // Zur Übersicht zurück
        return redirect()
            ->route('collections.index')
            ->with('success', 'Sammlung wurde gelöscht.');
    }
}
