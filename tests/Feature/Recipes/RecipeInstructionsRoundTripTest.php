<?php

namespace Tests\Feature\Recipes;

use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Regressionstests zu F-14 und F-66 (ADR-001): Schritte und Notizen überstehen
 * store -> edit -> update (unverändert) -> edit verlustfrei.
 *
 * Format in recipes.instructions: erste Zeile eines Schritts nummeriert („1. “),
 * jede weitere Zeile mit vier Leerzeichen eingerückt; „Notizen:“ nur uneingerückt
 * am Zeilenanfang als Abschnittskopf; Notizzeilen werden unverändert gelesen.
 *
 * Vertrag zum Frontend: edit liefert `steps` als Liste von Strings (mehrzeilige
 * Schritte mit "\n") und `notes` als EIN String (Zeilen mit "\n") oder null;
 * show liefert `steps` ebenso und `notes` als Liste von Zeilen.
 *
 * Der Schreiber und der Parser sind private Methoden des RecipeController;
 * ein Unit-Test direkt dagegen wäre nur per Reflection möglich und entfällt
 * deshalb – die Fälle laufen hier über die echten Routen.
 */
#[Group('F-14')]
#[Group('F-66')]
class RecipeInstructionsRoundTripTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    /**
     * [gesendete Schritte, gesendete Notizen, erwartete Schritte, erwartete Notizen (edit)]
     *
     * @return array<string, array{0: list<string>, 1: string, 2: list<string>, 3: string|null}>
     */
    public static function roundTripCases(): array
    {
        return [
            'einzeilige Schritte ohne Notizen' => [
                ['Schneiden', 'Kochen'], '',
                ['Schneiden', 'Kochen'], null,
            ],
            'mehrzeiliger Schritt' => [
                ["Teig kneten\nund ruhen lassen", 'Backen'], '',
                ["Teig kneten\nund ruhen lassen", 'Backen'], null,
            ],
            'Leerzeile im Schritt' => [
                ["Absatz eins\n\nAbsatz zwei", 'Fertig'], '',
                ["Absatz eins\n\nAbsatz zwei", 'Fertig'], null,
            ],
            'Zeile „Notizen:“ im Schritt' => [
                ['Schneiden', "Kochen\nNotizen:\nAbschmecken", 'Servieren'], 'Echte Notiz',
                ['Schneiden', "Kochen\nNotizen:\nAbschmecken", 'Servieren'], 'Echte Notiz',
            ],
            'Zeile „Notizen“ im Schritt' => [
                ["Kochen\nNotizen", 'Servieren'], '',
                ["Kochen\nNotizen", 'Servieren'], null,
            ],
            'Schritt besteht nur aus „Notizen:“ bzw. „Notizen“' => [
                ['Notizen:', 'Notizen', 'Servieren'], '',
                ['Notizen:', 'Notizen', 'Servieren'], null,
            ],
            'Schritt beginnt mit Zahl und Punkt' => [
                ['2. Variante', "Erst so\n2. Variante: anders", '1.5 kg Mehl'], '',
                ['2. Variante', "Erst so\n2. Variante: anders", '1.5 kg Mehl'], null,
            ],
            'Zeilen mit führenden Leerzeichen' => [
                ["Zutaten mischen:\n  - Mehl\n    - Zucker\n\t- Salz"], '',
                ["Zutaten mischen:\n  - Mehl\n    - Zucker\n\t- Salz"], null,
            ],
            'Windows-Zeilenenden' => [
                ["Zeile eins\r\nZeile zwei", 'Zwei'], "Tipp eins\r\nTipp zwei",
                ["Zeile eins\nZeile zwei", 'Zwei'], "Tipp eins\nTipp zwei",
            ],
            'mehrzeilige und nummerierte Notizen' => [
                ['Kochen'], "1. Tipp\n2. Tipp\nOhne Nummer",
                ['Kochen'], "1. Tipp\n2. Tipp\nOhne Nummer",
            ],
            'Notizen mit Zeile „Notizen:“' => [
                ['Kochen'], "Erste\nNotizen:\nZweite",
                ['Kochen'], "Erste\nNotizen:\nZweite",
            ],
            'Notizen: Leerzeilen und Randleerzeichen werden wie bisher normalisiert' => [
                ['Kochen'], "  Erste  \n\n   Zweite",
                ['Kochen'], "Erste\nZweite",
            ],
        ];
    }

    /**
     * @param  list<string>  $steps
     * @param  list<string>  $expectedSteps
     */
    #[DataProvider('roundTripCases')]
    public function test_store_edit_update_edit_round_trip(array $steps, string $notes, array $expectedSteps, ?string $expectedNotes): void
    {
        $this->actingAsHost();

        $this->post(route('recipes.store'), $this->recipePayload([
            'steps' => $steps,
            'notes' => $notes,
        ]))->assertSessionHasNoErrors();

        $recipe = Recipe::sole();
        $storedAfterCreate = $recipe->instructions;

        $firstEdit = $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe'];
        $this->assertSame($expectedSteps, $firstEdit['steps'], 'edit nach store');
        $this->assertSame($expectedNotes, $firstEdit['notes'], 'edit nach store');

        $show = $this->pageProps($this->get(route('recipes.show', $recipe)))['recipe'];
        $this->assertSame($expectedSteps, $show['steps'], 'show nach store');
        $this->assertSame(
            $expectedNotes === null ? [] : explode("\n", $expectedNotes),
            $show['notes'],
            'show nach store'
        );

        // Formularwerte unverändert zurücksenden (wie RecipeForm.vue)
        $this->post(route('recipes.update', $recipe), $this->recipePayload([
            'steps' => $firstEdit['steps'],
            'notes' => $firstEdit['notes'] ?? '',
        ]))->assertSessionHasNoErrors();

        $secondEdit = $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe'];
        $this->assertSame($firstEdit['steps'], $secondEdit['steps'], 'edit nach update');
        $this->assertSame($firstEdit['notes'], $secondEdit['notes'], 'edit nach update');
        $this->assertSame($storedAfterCreate, $recipe->fresh()->instructions, 'Speicherformat stabil');
    }

    public function test_storage_format_uses_indented_continuation_lines(): void
    {
        $this->actingAsHost();

        $this->post(route('recipes.store'), $this->recipePayload([
            'steps' => ["Teig kneten\n\nund ruhen lassen", "Notizen:\n2. Variante"],
            'notes' => "1. Tipp\nZweiter Tipp",
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            "1. Teig kneten\n    \n    und ruhen lassen\n2. Notizen:\n    2. Variante\nNotizen:\n1. Tipp\nZweiter Tipp",
            Recipe::sole()->instructions
        );
    }

    public function test_single_line_steps_are_stored_exactly_as_before(): void
    {
        $this->actingAsHost();

        $this->post(route('recipes.store'), $this->recipePayload([
            'steps' => ['C', 'A', 'B'],
            'notes' => "Notiz eins\n\nNotiz zwei",
        ]))->assertSessionHasNoErrors();

        // Altformat bleibt für einzeilige Schritte byte-gleich
        $this->assertSame("1. C\n2. A\n3. B\nNotizen:\nNotiz eins\nNotiz zwei", Recipe::sole()->instructions);
    }

    /**
     * Altbestand (gespeichert vom bisherigen Schreiber, keine Einrückung):
     * wird gelesen wie bisher. Werte: [instructions, erwartete Schritte, erwartete Notizzeilen].
     *
     * @return array<string, array{0: string, 1: list<string>, 2: list<string>}>
     */
    public static function legacyCases(): array
    {
        return [
            'nummeriert mit Leerzeile und Notizen' => [
                "1. Zwiebeln schneiden\n2. Anbraten\n\n3. Servieren\nNotizen:\nHält 3 Tage\nAuch kalt lecker",
                ['Zwiebeln schneiden', 'Anbraten', 'Servieren'],
                ['Hält 3 Tage', 'Auch kalt lecker'],
            ],
            'bereits zerfallener mehrzeiliger Schritt bleibt zerfallen' => [
                "1. Teig kneten\nund ruhen lassen\n2. Backen",
                ['Teig kneten', 'und ruhen lassen', 'Backen'],
                [],
            ],
            'ohne Nummern, Kopf ohne Doppelpunkt, CRLF' => [
                "Alles mischen\r\nnotizen\r\nohne Doppelpunkt",
                ['Alles mischen'],
                ['ohne Doppelpunkt'],
            ],
            'Schritt „Notizen“ mit Nummer ist kein Kopf' => [
                "1. Schneiden\n2. Notizen\n3. Kochen",
                ['Schneiden', 'Notizen', 'Kochen'],
                [],
            ],
            'Zeile mit weniger Leerzeichen als die Einrückung' => [
                "1. Eins\n  2. Zwei\n   Drei",
                ['Eins', 'Zwei', 'Drei'],
                [],
            ],
            'F-66: nummerierte Notizzeilen behalten ihre Nummer' => [
                "1. Linsen waschen.\nNotizen:\n1. Tipp\n2. Tipp",
                ['Linsen waschen.'],
                ['1. Tipp', '2. Tipp'],
            ],
        ];
    }

    /**
     * @param  list<string>  $expectedSteps
     * @param  list<string>  $expectedNotes
     */
    #[DataProvider('legacyCases')]
    public function test_legacy_instructions_are_read_as_before(string $instructions, array $expectedSteps, array $expectedNotes): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create(['instructions' => $instructions]);

        $show = $this->pageProps($this->get(route('recipes.show', $recipe)))['recipe'];
        $this->assertSame($expectedSteps, $show['steps']);
        $this->assertSame($expectedNotes, $show['notes']);

        $form = $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe'];
        $this->assertSame($expectedSteps, $form['steps']);
        $this->assertSame($expectedNotes === [] ? null : implode("\n", $expectedNotes), $form['notes']);

        // Unverändert speichern -> gleiche Formularwerte
        $this->post(route('recipes.update', $recipe), $this->recipePayload([
            'steps' => $form['steps'],
            'notes' => $form['notes'] ?? '',
        ]))->assertSessionHasNoErrors();

        $again = $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe'];
        $this->assertSame($form['steps'], $again['steps']);
        $this->assertSame($form['notes'], $again['notes']);
    }

    public function test_empty_instructions_yield_empty_steps_and_notes(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create(['instructions' => '']); // Spalte ist NOT NULL

        $show = $this->pageProps($this->get(route('recipes.show', $recipe)))['recipe'];
        $this->assertSame([], $show['steps']);
        $this->assertSame([], $show['notes']);

        $form = $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe'];
        $this->assertSame([], $form['steps']);
        $this->assertNull($form['notes']);
    }
}
