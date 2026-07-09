<?php

namespace App\Http\Controllers;

use App\Jobs\ImportTikTokRecipe;
use App\Models\RecipeImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipeImportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'regex:/^https?:\/\/((www|vm|vt|m)\.)?tiktok\.com\//i'],
        ], [
            'url.regex' => 'Bitte einen gültigen TikTok-Link einfügen.',
        ]);

        $import = RecipeImport::create([
            'user_id' => $request->user()->id,
            'url' => $validated['url'],
            'status' => 'pending',
        ]);

        ImportTikTokRecipe::dispatch($import);

        return response()->json(['id' => $import->id]);
    }

    public function show(Request $request, RecipeImport $recipeImport): JsonResponse
    {
        abort_unless($recipeImport->user_id === $request->user()->id, 403);

        return response()->json([
            'id' => $recipeImport->id,
            'status' => $recipeImport->status,
            'source' => $recipeImport->source,
            'error' => $recipeImport->error,
        ]);
    }
}
