<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;

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
}
