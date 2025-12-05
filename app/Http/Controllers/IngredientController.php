<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IngredientController extends Controller
{
    public function index()
    {
        $ingredients = Ingredient::with('categories')
            ->orderBy('name')
            ->get()
            ->map(function (Ingredient $ingredient) {
                return [
                    'id'       => $ingredient->id,
                    'name'     => $ingredient->name,
                    'category' => $ingredient->categories
                        ->pluck('name')
                        ->values()
                        ->all(),
                ];
            });

        return Inertia::render('IngredientSearch', [
            'ingredients' => $ingredients,
        ]);
    }
}
