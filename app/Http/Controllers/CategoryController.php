<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
        ]);

        $category = Category::create([
            'name'        => $data['name'],
            'description' => null,
        ]);

        return response()->json([
            'id'   => $category->id,
            'name' => $category->name,
        ], 201);
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:categories,name,' . $category->id,
            ],
        ]);

        $category->update([
            'name' => $data['name'],
        ]);

        return response()->json([
            'id'   => $category->id,
            'name' => $category->name,
        ]);
    }

    public function destroy(Category $category)
    {
        // Pivot zu Recipes lösen, wenn Beziehung existiert
        if (method_exists($category, 'recipes')) {
            $category->recipes()->detach();
        }

        $category->delete();

        return response()->json([
            'status' => 'deleted',
        ]);
    }
}
