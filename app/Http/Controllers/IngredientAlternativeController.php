<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\IngredientAlternative;
use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Http\Request;

class IngredientAlternativeController extends Controller
{
    public function index(Recipe $recipe, Ingredient $ingredient)
    {
        $alternatives = IngredientAlternative::with('alternativeIngredient')
            ->where('recipe_id', $recipe->id)
            ->where('ingredient_id', $ingredient->id)
            ->get()
            ->map(function (IngredientAlternative $alt) {
                return [
                    'id'                      => $alt->id,
                    'ingredient_id'           => $alt->ingredient_id,
                    'alternative_ingredient_id' => $alt->alternative_ingredient_id,
                    'alternative'             => [
                        'id'   => $alt->alternativeIngredient?->id,
                        'name' => $alt->alternativeIngredient?->name,
                    ],
                ];
            });

        return response()->json($alternatives);
    }

    public function store(Request $request, Recipe $recipe, Ingredient $ingredient)
    {
        $data = $request->validate([
            'alternative_ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
        ]);

        $alt = IngredientAlternative::create([
            'recipe_id'              => $recipe->id,
            'ingredient_id'          => $ingredient->id,
            'alternative_ingredient_id' => $data['alternative_ingredient_id'],
            'note'                   => null,
        ]);

        $alt->load('alternativeIngredient');

        return response()->json([
            'id'                      => $alt->id,
            'ingredient_id'           => $alt->ingredient_id,
            'alternative_ingredient_id' => $alt->alternative_ingredient_id,
            'alternative'             => [
                'id'   => $alt->alternativeIngredient?->id,
                'name' => $alt->alternativeIngredient?->name,
            ],
        ], 201);
    }

    public function destroy(IngredientAlternative $alternative)
    {
        $alternative->delete();

        return response()->json(['status' => 'deleted']);
    }
}
