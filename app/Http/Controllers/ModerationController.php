<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeReport;
use App\Services\CommunityRecipes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * E18 / ADR-005: Moderation der Community-Rezepte („Zu prüfen“, nur Host).
 *
 * Die Routen liegen in der Gruppe ['auth', 'verified', 'is_host'];
 * zusätzlich prüft die Policy `moderate` je Rezept.
 */
class ModerationController extends Controller
{
    public function index(Request $request): Response
    {
        $pending = Recipe::query()
            ->where('type', Recipe::TYPE_COMMUNITY)
            ->where('status', Recipe::STATUS_PENDING)
            ->with([
                'author:id,name',
                'categories',
                'images' => fn ($q) => $q->orderBy('id')->limit(1),
                'recipeIngredients.ingredient',
            ])
            ->withCount(['reports as open_reports_count' => fn ($q) => $q->where('status', RecipeReport::STATUS_OPEN)])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Recipe $recipe) => [
                'id' => $recipe->id,
                'name' => $recipe->title,
                'description' => $recipe->description,
                'author' => $recipe->author?->name,
                'submittedAt' => $recipe->submitted_at?->toDateString(),
                'image' => $recipe->images->first()?->image_path
                    ? Storage::disk('public')->url($recipe->images->first()->image_path)
                    : null,
                'categories' => $recipe->categories->pluck('name')->all(),
                'ingredients' => $recipe->recipeIngredients
                    ->map(fn ($ri) => [
                        'id' => $ri->ingredient?->id,
                        'name' => $ri->ingredient?->name,
                        'amount' => $ri->note,
                        'proposed' => (bool) $ri->ingredient?->isProposed(),
                    ])
                    ->values()
                    ->all(),
                'openReports' => $recipe->open_reports_count,
                'wasReviewed' => $recipe->reviewed_at !== null,
            ])
            ->values();

        $reports = RecipeReport::query()
            ->where('status', RecipeReport::STATUS_OPEN)
            ->with(['recipe:id,title,status,type,user_id', 'recipe.author:id,name'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (RecipeReport $report) => [
                'id' => $report->id,
                'reason' => $report->reasonLabel(),
                'message' => $report->message,
                'contactEmail' => $report->contact_email,
                'byUser' => $report->user_id !== null,
                'createdAt' => $report->created_at?->toDateString(),
                'recipe' => [
                    'id' => $report->recipe?->id,
                    'name' => $report->recipe?->title,
                    'status' => $report->recipe?->status,
                    'author' => $report->recipe?->author?->name,
                ],
            ])
            ->values();

        return Inertia::render('moderation/Index', [
            'pending' => $pending,
            'reports' => $reports,
            'catalog' => fn () => Ingredient::approved()->orderBy('name')->pluck('name')->all(),
        ]);
    }

    public function approve(Request $request, Recipe $recipe): RedirectResponse
    {
        Gate::authorize('moderate', $recipe);

        CommunityRecipes::approve($recipe, $request->user());

        return back()->with('success', "„{$recipe->title}“ ist freigegeben und jetzt öffentlich.");
    }

    public function reject(Request $request, Recipe $recipe): RedirectResponse
    {
        Gate::authorize('moderate', $recipe);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'reason.required' => 'Bitte gib einen Grund an – der Autor sieht ihn.',
            'reason.min' => 'Bitte beschreibe den Grund etwas genauer (mindestens 5 Zeichen).',
        ]);

        CommunityRecipes::reject($recipe, $request->user(), trim($validated['reason']));

        return back()->with('success', "„{$recipe->title}“ wurde abgelehnt. Der Autor sieht den Grund.");
    }

    /** Nach einer Meldung ausblenden: zurück auf „wird geprüft“. */
    public function hide(Request $request, Recipe $recipe): RedirectResponse
    {
        Gate::authorize('moderate', $recipe);

        CommunityRecipes::hide($recipe, $request->user());

        return back()->with('success', "„{$recipe->title}“ ist ausgeblendet und wartet auf erneute Prüfung.");
    }

    public function resolveReport(Request $request, RecipeReport $report): RedirectResponse
    {
        $report->forceFill([
            'status' => RecipeReport::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ])->save();

        return back()->with('success', 'Meldung als erledigt markiert.');
    }

    /** Vorgeschlagene Zutat vor der Freigabe umbenennen (nur Vorschläge). */
    public function renameIngredient(Request $request, Ingredient $ingredient): RedirectResponse
    {
        abort_unless($ingredient->isProposed(), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('ingredients', 'name')->ignore($ingredient->id)],
        ], [
            'name.unique' => 'Diese Zutat gibt es schon. Ordne den Vorschlag ihr zu, statt ihn umzubenennen.',
        ]);

        $ingredient->update(['name' => trim($validated['name'])]);

        return back()->with('success', 'Vorschlag umbenannt.');
    }

    /** Vorschlag einer vorhandenen Katalog-Zutat zuordnen (Dublette vermeiden). */
    public function mergeIngredient(Request $request, Ingredient $ingredient): RedirectResponse
    {
        abort_unless($ingredient->isProposed(), 404);

        $validated = $request->validate([
            'target' => ['required', 'string', 'max:255'],
        ]);

        $target = Ingredient::approved()->where('name', trim($validated['target']))->first();

        if ($target === null) {
            return back()->withErrors(['target' => 'Diese Zutat steht nicht im Katalog.']);
        }

        CommunityRecipes::mergeProposal($ingredient, $target);

        return back()->with('success', "Vorschlag „{$ingredient->name}“ wurde „{$target->name}“ zugeordnet.");
    }
}
