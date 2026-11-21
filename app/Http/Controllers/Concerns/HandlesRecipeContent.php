<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Ingredient;
use App\Models\IngredientAlternative;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\RecipeImageFiles;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * E18: gemeinsame Bausteine von RecipeController (Host-Rezeptbuch) und
 * CommunityRecipeController. Unverändert aus RecipeController übernommen
 * (ADR-001 Textformat, F-13 Zutaten/Alternativen, F-19 Bilddateien,
 * F-22 Query-Normalisierung, F-23 Pagination).
 */
trait HandlesRecipeContent
{
    /**
     * Einrückung, mit der jede weitere Zeile eines mehrzeiligen Schritts
     * gespeichert wird (ADR-001). Vier Leerzeichen: Der bisherige Schreiber
     * hat jede Zeile getrimmt, Altbestand beginnt deshalb nie mit Leerzeichen.
     */
    private const STEP_CONTINUATION_INDENT = '    ';

    /**
     * Abschnittskopf der Notizen – gilt nur uneingerückt am Zeilenanfang.
     */
    private const NOTES_HEADER = 'Notizen:';

    /**
     * Speichert die Zutaten eines Rezepts samt Alternativzutaten exakt nach
     * Payload (F-13). Wird von store() und update() innerhalb der jeweiligen
     * Transaktion aufgerufen: Vorhandene Zeilen werden ersetzt, Alternativen
     * von Zutaten, die nicht mehr im Rezept stehen, verschwinden.
     *
     * Ein fehlender Schlüssel `alternativeIngredientIds` bedeutet „keine
     * Alternativen“ (FormData überträgt leere Arrays nicht). Doppelte IDs und
     * die Zutat als ihre eigene Alternative werden still verworfen.
     *
     * E18: `$resolveIngredient` findet bzw. erstellt die Zutat zum Namen
     * (Host: Katalog; Community: Katalog oder Vorschlag).
     *
     * @param  array<int, array{name?: string, amount?: string|null, alternativeIngredientIds?: array<int, int|string>}>  $rows
     * @param  Closure(string): Ingredient  $resolveIngredient
     */
    private function syncIngredientsAndAlternatives(Recipe $recipe, array $rows, Closure $resolveIngredient): void
    {
        $recipe->ingredientAlternatives()->delete();
        $recipe->recipeIngredients()->delete();

        /** @var array<int, array<int, true>> $savedAlternatives ingredient_id => [alternative_id => true] */
        $savedAlternatives = [];

        foreach ($rows as $ingredientRow) {
            $name = trim($ingredientRow['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $ingredient = $resolveIngredient($name);

            // Menge vorerst als Freitext in note ablegen (z. B. "200 g")
            RecipeIngredient::create([
                'recipe_id' => $recipe->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => null,
                'unit' => null,
                'note' => $ingredientRow['amount'] ?? null,
            ]);

            foreach ($ingredientRow['alternativeIngredientIds'] ?? [] as $alternativeId) {
                $alternativeId = (int) $alternativeId;

                if ($alternativeId === $ingredient->id || isset($savedAlternatives[$ingredient->id][$alternativeId])) {
                    continue;
                }

                IngredientAlternative::create([
                    'recipe_id' => $recipe->id,
                    'ingredient_id' => $ingredient->id,
                    'alternative_ingredient_id' => $alternativeId,
                    'note' => null,
                ]);

                $savedAlternatives[$ingredient->id][$alternativeId] = true;
            }
        }
    }

    /**
     * Entfernt Bilddateien vom public-Disk. Wird erst NACH dem Commit (bzw.
     * nach einem Rollback für neu hochgeladene Dateien) aufgerufen (F-19).
     * Pfade, die noch von einem anderen Datensatz referenziert werden, bleiben
     * erhalten. Fehler werden geloggt und lassen den Request nicht scheitern.
     *
     * @param  array<int, string>  $paths
     */
    private function deletePublicImageFiles(array $paths): void
    {
        RecipeImageFiles::delete($paths);
    }

    /**
     * Schreibt Schritte und Notizen in das Textformat von `recipes.instructions`
     * (ADR-001):
     *
     *   1. Erste Zeile von Schritt 1
     *       Fortsetzungszeile von Schritt 1   (STEP_CONTINUATION_INDENT + Text)
     *   2. Schritt 2
     *   Notizen:
     *   Notizzeile (getrimmt, Leerzeilen entfallen)
     *
     * Gegenstück: parseInstructions().
     *
     * @param  array<int, string>  $steps
     */
    private function buildInstructionsText(array $steps, ?string $notes): string
    {
        $lines = [];

        // Nummerierte Schritte; weitere Zeilen eines Schritts eingerückt
        foreach (array_values($steps) as $index => $step) {
            $stepLines = preg_split('/\r\n|\r|\n/', trim((string) $step));

            $lines[] = ($index + 1).'. '.rtrim((string) array_shift($stepLines));

            foreach ($stepLines as $continuation) {
                $lines[] = self::STEP_CONTINUATION_INDENT.rtrim($continuation);
            }
        }

        // Optional: Notizen anhängen
        if ($notes !== null && trim($notes) !== '') {
            $lines[] = self::NOTES_HEADER;

            foreach (preg_split('/\r\n|\r|\n/', $notes) as $noteLine) {
                $noteLine = trim($noteLine);
                if ($noteLine === '') {
                    continue;
                }
                $lines[] = $noteLine;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Liest `recipes.instructions` in Schritte und Notizzeilen (ADR-001).
     * Einziger Leser für show() und edit(); Gegenstück zu buildInstructionsText().
     *
     * - Zeile mit STEP_CONTINUATION_INDENT: Fortsetzung des vorherigen Schritts
     *   (Einrückung wird entfernt, Rest bleibt inkl. führender Leerzeichen).
     * - Uneingerückte Zeile „Notizen:“ / „Notizen“ (Groß-/Kleinschreibung egal):
     *   Beginn der Notizen; alle weiteren Zeilen sind Notizzeilen.
     * - Sonst (auch Altbestand): getrimmt, Leerzeilen entfallen, führende
     *   Nummer „1. “ wird entfernt, jede Zeile ist ein eigener Schritt.
     * - Notizzeilen: getrimmt, Leerzeilen entfallen, Nummern bleiben erhalten.
     *
     * @return array{0: list<string>, 1: list<string>} [Schritte, Notizzeilen]
     */
    private function parseInstructions(?string $instructions): array
    {
        if ($instructions === null || $instructions === '') {
            return [[], []];
        }

        $steps = [];
        $notes = [];
        $inNotes = false;
        $indentLength = strlen(self::STEP_CONTINUATION_INDENT);

        foreach (preg_split('/\r\n|\r|\n/', $instructions) as $rawLine) {
            if ($inNotes) {
                $line = trim($rawLine);
                if ($line !== '') {
                    $notes[] = $line;
                }

                continue;
            }

            if ($steps !== [] && str_starts_with($rawLine, self::STEP_CONTINUATION_INDENT)) {
                $steps[array_key_last($steps)] .= "\n".rtrim(substr($rawLine, $indentLength));

                continue;
            }

            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            // Abschnittskopf nur uneingerückt am Zeilenanfang
            if (preg_match('/^'.rtrim(self::NOTES_HEADER, ':').':?\s*$/i', $rawLine)) {
                $inNotes = true;

                continue;
            }

            // Nummerierung "1. Waschen" -> "Waschen"
            $steps[] = preg_match('/^\d+\.\s*(.+)$/', $line, $matches) ? $matches[1] : $line;
        }

        return [$steps, $notes];
    }

    /**
     * Macht aus einem Query-Wert eine flache Liste getrimmter, nicht leerer
     * Strings. Nicht-String-Elemente (z. B. verschachtelte Arrays) werden
     * verworfen (F-22).
     *
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            $value = [$value];
        }

        $strings = array_filter($value, 'is_string');

        return array_values(array_filter(array_map('trim', $strings)));
    }

    /**
     * Props für die Seitennavigation (components/ui/Pagination.vue). Alle
     * URLs kommen aus dem Paginator (`withQueryString`) und enthalten damit
     * sämtliche Filter. `pages`: erste, letzte und die Nachbarn der aktuellen
     * Seite; größere Lücken als `page: null` (…), eine Lücke von genau einer
     * Seite wird stattdessen als diese Seite gezeigt.
     *
     * @return array{currentPage: int, lastPage: int, perPage: int, total: int, from: int|null, to: int|null, prevUrl: string|null, nextUrl: string|null, pages: list<array{page: int|null, url: string|null, current: bool}>}
     */
    private function paginationProps(LengthAwarePaginator $paginator): array
    {
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();

        $numbers = array_values(array_unique(array_filter(
            [1, $current - 1, $current, $current + 1, $last],
            fn (int $n) => $n >= 1 && $n <= $last,
        )));
        sort($numbers);

        $pages = [];
        $previous = 0;
        foreach ($numbers as $n) {
            if ($n - $previous === 2) {
                $pages[] = ['page' => $n - 1, 'url' => $paginator->url($n - 1), 'current' => false];
            } elseif ($n - $previous > 2) {
                $pages[] = ['page' => null, 'url' => null, 'current' => false];
            }
            $pages[] = ['page' => $n, 'url' => $paginator->url($n), 'current' => $n === $current];
            $previous = $n;
        }

        return [
            'currentPage' => $current,
            'lastPage' => $last,
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'prevUrl' => $paginator->previousPageUrl(),
            'nextUrl' => $paginator->nextPageUrl(),
            'pages' => $pages,
        ];
    }

    /**
     * F-22/F-23: gültige Seitennummer aus dem Query (sonst 1).
     */
    private function requestedPage(mixed $value): int
    {
        $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $page === false ? 1 : $page;
    }
}
