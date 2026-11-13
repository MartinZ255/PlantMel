<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
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
                    'id'       => $ingredient->id,
                    'name'     => $ingredient->name,
                ];
            });

        return Inertia::render('IngredientSearch', [
            'ingredients' => $ingredients,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:ingredients,name'],
            'description' => ['nullable', 'string'],
        ]);

        $ingredient = Ingredient::create($data);

        return response()->json([
            'id'          => $ingredient->id,
            'name'        => $ingredient->name,
            'description' => $ingredient->description,
        ], 201);
    }

    public function update(Request $request, Ingredient $ingredient)
    {
        $data = $request->validate([
            'name'        => [
                'required',
                'string',
                'max:255',
                'unique:ingredients,name,' . $ingredient->id,
            ],
            'description' => ['nullable', 'string'],
        ]);

        $ingredient->update($data);

        return response()->json([
            'id'          => $ingredient->id,
            'name'        => $ingredient->name,
            'description' => $ingredient->description,
        ]);
    }

    public function destroy(Ingredient $ingredient)
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

        $ingredient->delete();

        return response()->json([
            'status' => 'deleted',
        ]);
    }

}
