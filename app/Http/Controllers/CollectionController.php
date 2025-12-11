<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;


class CollectionController extends Controller
{
    public function index(Request $request)
    {
        $collections = Collection::query()
            ->where('user_id', $request->user()->id)
            ->withCount('recipes')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Collection $collection) {
                return [
                    'id' => $collection->id,
                    'name' => $collection->name,
                    'description' => $collection->description,
                    'recipeCount' => $collection->recipes()->count(),
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

        $collection->load([
            'recipes.categories',
            'recipes.ratings',
            'recipes.images',
        ]);

        $recipes = $collection->recipes->map(function ($recipe) {
            $avgRating = $recipe->ratings->avg('value');

            $firstImage = $recipe->images->first();
            $imageUrl = $firstImage?->image_path
                ? Storage::url($firstImage->image_path)
                : null;

            return [
                'id' => $recipe->id,
                'name' => $recipe->title,
                'rating' => $avgRating ? (int)round($avgRating) : 0,
                'tags' => $recipe->categories->pluck('name')->all(),
                'time' => $recipe->duration_minutes
                    ? $recipe->duration_minutes . ' Min'
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

    public function addRecipe(Request $request, Collection $collection)
    {
        if ($collection->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'recipe_id' => ['required', 'exists:recipes,id'],
        ]);

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
