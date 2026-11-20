<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            // max:100 entspricht der Spalte categories.name (F-22)
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
        ]);

        $category = Category::create([
            'name' => $data['name'],
            'description' => null,
        ]);

        return response()->json([
            'id' => $category->id,
            'name' => $category->name,
        ], 201);
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100', // Spalte categories.name (F-22)
                'unique:categories,name,'.$category->id,
            ],
        ]);

        $category->update([
            'name' => $data['name'],
        ]);

        return response()->json([
            'id' => $category->id,
            'name' => $category->name,
            // F-07: Umbenennen wirkt global – Zahl der betroffenen Rezepte für einen Hinweis
            'recipes_count' => $category->recipes()->count(),
        ]);
    }

    /**
     * ADR-002 (F-07): Löschen nur mit Request-Parameter `confirmed` (truthy).
     * Ohne Bestätigung: 409 mit der Zahl der Rezepte, aus denen die Kategorie
     * entfernt würde; es wird nichts gelöscht.
     */
    public function destroy(Request $request, Category $category)
    {
        if (! $request->boolean('confirmed')) {
            $recipesCount = $category->recipes()->count();

            return response()->json([
                'message' => $this->confirmationMessage($category, $recipesCount),
                'requires_confirmation' => true,
                'recipes_count' => $recipesCount,
            ], 409);
        }

        DB::transaction(function () use ($category) {
            // Pivot zu Recipes lösen, wenn Beziehung existiert
            if (method_exists($category, 'recipes')) {
                $category->recipes()->detach();
            }

            $category->delete();
        });

        return response()->json([
            'status' => 'deleted',
        ]);
    }

    private function confirmationMessage(Category $category, int $recipesCount): string
    {
        $usage = match ($recipesCount) {
            0 => 'wird in keinem Rezept verwendet',
            1 => 'wird in einem Rezept verwendet und dort entfernt',
            default => "wird in {$recipesCount} Rezepten verwendet und dort überall entfernt",
        };

        return "Die Kategorie „{$category->name}“ {$usage}. Bitte bestätige das Löschen.";
    }
}
