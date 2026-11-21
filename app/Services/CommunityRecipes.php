<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientAlternative;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeReport;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * E18 / ADR-005: Fachlogik der Community-Rezepte – Zutaten-Vorschläge,
 * Statuswechsel der Moderation und Löschen (Rezept, Konto).
 *
 * Regeln:
 * - Unbekannte Zutaten eines Community-Rezepts werden als Vorschlag
 *   (`ingredients.status = proposed`) angelegt; sie erscheinen nicht im
 *   Katalog, im Autocomplete anderer oder in der Zutatensuche.
 * - Mit der Freigabe eines Rezepts werden seine Vorschläge Teil des Katalogs.
 * - Vorschläge, auf die kein Rezept mehr verweist, werden verworfen
 *   (nach Löschen, Bearbeiten, Zuordnen; siehe sweepUnusedProposals()).
 */
class CommunityRecipes
{
    /**
     * Host-Formular (unverändert): Zutat per exaktem Namen finden oder
     * anlegen. Nutzt der Host dabei einen offenen Vorschlag, übernimmt er ihn
     * damit bewusst in den Katalog.
     *
     * @return Closure(string): Ingredient
     */
    public static function hostIngredientResolver(): Closure
    {
        return function (string $name): Ingredient {
            $ingredient = Ingredient::firstOrCreate(['name' => $name]);

            if ($ingredient->isProposed()) {
                $ingredient->forceFill(['status' => Ingredient::STATUS_APPROVED])->save();
            }

            return $ingredient;
        };
    }

    /**
     * Community-Formular: Katalog-Zutat (exakt oder ohne Groß-/Klein-
     * schreibung) verwenden, sonst einen bestehenden Vorschlag gleichen
     * Namens, sonst einen neuen Vorschlag anlegen.
     *
     * @return Closure(string): Ingredient
     */
    public static function communityIngredientResolver(User $proposer): Closure
    {
        return function (string $name) use ($proposer): Ingredient {
            $exact = Ingredient::where('name', $name)->first();
            if ($exact !== null && ! $exact->isProposed()) {
                return $exact;
            }

            $folded = Ingredient::approved()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->orderBy('id')
                ->first();
            if ($folded !== null) {
                return $folded;
            }

            if ($exact !== null) {
                return $exact; // gemeinsamer, noch offener Vorschlag
            }

            $ingredient = new Ingredient(['name' => $name]);
            $ingredient->forceFill([
                'status' => Ingredient::STATUS_PROPOSED,
                'proposed_by' => $proposer->id,
            ])->save();

            return $ingredient;
        };
    }

    /**
     * Offene Vorschläge, die ein Rezept verwendet (als Zutat oder Basis einer
     * Alternative), nach Name sortiert.
     *
     * @return Collection<int, Ingredient>
     */
    public static function proposalsOf(Recipe $recipe): Collection
    {
        return Ingredient::proposed()
            ->where(function ($q) use ($recipe) {
                $q->whereIn('id', RecipeIngredient::where('recipe_id', $recipe->id)->select('ingredient_id'))
                    ->orWhereIn('id', IngredientAlternative::where('recipe_id', $recipe->id)->select('ingredient_id'));
            })
            ->orderBy('name')
            ->get();
    }

    public static function approve(Recipe $recipe, User $host): void
    {
        DB::transaction(function () use ($recipe, $host) {
            $recipe->forceFill([
                'status' => Recipe::STATUS_APPROVED,
                'rejection_reason' => null,
                'reviewed_at' => now(),
                'reviewed_by' => $host->id,
            ])->save();

            // Vorschläge dieses Rezepts werden Teil des Katalogs
            $ids = self::proposalsOf($recipe)->pluck('id');
            if ($ids->isNotEmpty()) {
                Ingredient::whereIn('id', $ids)->update(['status' => Ingredient::STATUS_APPROVED]);
            }

            // Freigabe erledigt offene Meldungen nicht automatisch: Sie
            // bleiben für den Host sichtbar, bis er sie abschließt.
        });
    }

    /**
     * Ablehnen mit Grund (für den Autor sichtbar). Die Vorschläge bleiben am
     * abgelehnten Rezept hängen (nicht im Katalog), damit der Autor es
     * korrigieren und erneut einreichen kann; sie verschwinden mit dem Rezept
     * oder sobald der Autor sie entfernt.
     */
    public static function reject(Recipe $recipe, User $host, string $reason): void
    {
        $recipe->forceFill([
            'status' => Recipe::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
            'reviewed_by' => $host->id,
        ])->save();

        self::sweepUnusedProposals();
    }

    /**
     * Nach einer Meldung ausblenden: zurück auf „wird geprüft“, bis der Host
     * erneut freigibt.
     */
    public static function hide(Recipe $recipe, User $host): void
    {
        $recipe->forceFill([
            'status' => Recipe::STATUS_PENDING,
            'reviewed_at' => now(),
            'reviewed_by' => $host->id,
        ])->save();
    }

    /**
     * Rezept samt Bildern löschen; Sammlungs-Einträge, Bewertungen und
     * Meldungen gehen per Kaskade mit. Ungenutzte Vorschläge werden danach
     * verworfen.
     */
    public static function delete(Recipe $recipe): void
    {
        $imagePaths = $recipe->images()->pluck('image_path')->filter()->all();

        DB::transaction(function () use ($recipe) {
            $recipe->collections()->detach();
            $recipe->delete();
        });

        RecipeImageFiles::delete($imagePaths);
        self::sweepUnusedProposals();
    }

    /**
     * Konto löschen: alle eigenen Community-Rezepte inkl. Bilder entfernen.
     *
     * @return int Anzahl gelöschter Rezepte
     */
    public static function deleteAllOf(User $user): int
    {
        $recipes = Recipe::where('type', Recipe::TYPE_COMMUNITY)
            ->where('user_id', $user->id)
            ->get();

        foreach ($recipes as $recipe) {
            self::delete($recipe);
        }

        return $recipes->count();
    }

    /**
     * Ordnet einen Vorschlag einer vorhandenen Katalog-Zutat zu (Dubletten
     * vermeiden): alle Verweise wandern auf die Ziel-Zutat, der Vorschlag
     * wird gelöscht.
     */
    public static function mergeProposal(Ingredient $proposal, Ingredient $target): void
    {
        DB::transaction(function () use ($proposal, $target) {
            RecipeIngredient::where('ingredient_id', $proposal->id)
                ->update(['ingredient_id' => $target->id]);

            // Alternativen: Eindeutigkeit (recipe, ingredient, alternative) wahren
            $rows = IngredientAlternative::where('ingredient_id', $proposal->id)->get();
            foreach ($rows as $row) {
                $duplicate = IngredientAlternative::where('recipe_id', $row->recipe_id)
                    ->where('ingredient_id', $target->id)
                    ->where('alternative_ingredient_id', $row->alternative_ingredient_id)
                    ->exists();

                if ($duplicate || (int) $row->alternative_ingredient_id === (int) $target->id) {
                    $row->delete();
                } else {
                    $row->update(['ingredient_id' => $target->id]);
                }
            }

            IngredientAlternative::where('alternative_ingredient_id', $proposal->id)
                ->update(['alternative_ingredient_id' => $target->id]);

            $proposal->delete();
        });
    }

    /**
     * Verwirft Vorschläge, auf die kein Rezept (Zutat oder Alternative) mehr
     * verweist. Katalog-Zutaten bleiben immer unangetastet.
     */
    public static function sweepUnusedProposals(): int
    {
        return Ingredient::proposed()
            ->whereNotIn('id', RecipeIngredient::select('ingredient_id'))
            ->whereNotIn('id', IngredientAlternative::select('ingredient_id'))
            ->whereNotIn('id', IngredientAlternative::select('alternative_ingredient_id'))
            ->delete();
    }

    /**
     * Zahl für das Badge „Zu prüfen“ in der Host-Navigation: Community-Rezepte,
     * die auf Freigabe warten oder offene Meldungen haben (je Rezept einmal).
     */
    public static function moderationCount(): int
    {
        return Recipe::where('type', Recipe::TYPE_COMMUNITY)
            ->where(fn ($q) => $q
                ->where('status', Recipe::STATUS_PENDING)
                ->orWhereHas('reports', fn ($r) => $r->where('status', RecipeReport::STATUS_OPEN)))
            ->count();
    }
}
