<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IngredientController extends Controller
{
    public function index()
    {
        $ingredients = Ingredient::orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Ingredient $ingredient) {
                return [
                    'id' => $ingredient->id,
                    'name' => $ingredient->name,
                ];
            });

        return Inertia::render('IngredientSearch', [
            'ingredients' => $ingredients,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:ingredients,name'],
            'description' => ['nullable', 'string'],
        ]);

        $ingredient = Ingredient::create($data);

        return response()->json([
            'id' => $ingredient->id,
            'name' => $ingredient->name,
            'description' => $ingredient->description,
        ], 201);
    }

    public function update(Request $request, Ingredient $ingredient)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:ingredients,name,'.$ingredient->id,
            ],
            'description' => ['nullable', 'string'],
        ]);

        $ingredient->update($data);

        return response()->json([
            'id' => $ingredient->id,
            'name' => $ingredient->name,
            'description' => $ingredient->description,
            // F-07: Umbenennen wirkt global – Zahl der betroffenen Rezepte für einen Hinweis
            'recipes_count' => $this->recipesUsingCount($ingredient),
        ]);
    }

    /**
     * Zahl der Rezepte, in denen die Zutat vorkommt: als Zutat des Rezepts oder
     * in dessen Alternativen (als Basiszutat oder als Alternative).
     */
    private function recipesUsingCount(Ingredient $ingredient): int
    {
        $id = $ingredient->id;

        return Recipe::query()
            ->whereHas('recipeIngredients', fn ($query) => $query->where('ingredient_id', $id))
            ->orWhereHas('ingredientAlternatives', fn ($query) => $query
                ->where('ingredient_id', $id)
                ->orWhere('alternative_ingredient_id', $id))
            ->count();
    }

    /**
     * Die 422-Fälle (in Rezept verwendet / als Alternative hinterlegt) haben
     * Vorrang. Sonst gilt ADR-002 (F-07, F-65): Löschen nur mit Request-Parameter
     * `confirmed` (truthy); ohne Bestätigung 409 mit der Zahl der Alternativen,
     * die per Kaskade mitgelöscht würden.
     */
    public function destroy(Request $request, Ingredient $ingredient)
    {
        // Löschen verhindern, wenn Zutat in Rezepten verwendet wird
        if ($ingredient->recipeIngredients()->exists()) {
            return response()->json([
                'message' => 'Die Zutat wird in mindestens einem Rezept verwendet und kann nicht gelöscht werden.',
            ], 422);
        }

        // F-18: Als Alternative genutzte Zutaten ebenfalls schützen
        // (ingredient_alternatives.alternative_ingredient_id hat kein onDelete).
        if ($ingredient->alternativeFor()->exists()) {
            return response()->json([
                'message' => 'Die Zutat ist in mindestens einem Rezept als Alternative hinterlegt und kann nicht gelöscht werden.',
            ], 422);
        }

        if (! $request->boolean('confirmed')) {
            // F-65: Alternativen, bei denen die Zutat Basiszutat ist (cascadeOnDelete)
            $alternativesCount = $ingredient->alternatives()->count();

            $usage = match ($alternativesCount) {
                0 => 'Die Zutat „'.$ingredient->name.'“ wird global gelöscht',
                1 => 'Mit der Zutat „'.$ingredient->name.'“ wird eine hinterlegte Alternative gelöscht',
                default => 'Mit der Zutat „'.$ingredient->name."“ werden {$alternativesCount} hinterlegte Alternativen gelöscht",
            };

            return response()->json([
                'message' => $usage.'. Bitte bestätige das Löschen.',
                'requires_confirmation' => true,
                'alternatives_count' => $alternativesCount,
            ], 409);
        }

        $ingredient->delete();

        return response()->json([
            'status' => 'deleted',
        ]);
    }
}
