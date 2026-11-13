<?php

namespace Tests\KnownDefects\Recipes;

use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Bekannter Defekt F-22: Eingaben, die zu 500 statt zu einer sauberen Antwort führen.
 * - `?search[]=a` -> TypeError in trim() (RecipeController.php:167,196)
 * - `ratings.*`-Schlüssel werden nicht gegen rating_dimensions geprüft (:348-349, :422-432)
 *   -> Fremdschlüssel-Verletzung beim Insert.
 *
 * Sollverhalten: kein 500; Liste antwortet 200 (Parameter ignoriert/normalisiert) oder
 * mit Validierungsfehler (422/302); unbekannte Dimension ergibt einen Validierungsfehler.
 * Gegenpart (Ist-Verhalten): Tests\Feature\Recipes\RecipeCurrentDefectBehaviourTest (…_f22).
 */
#[Group('known-defect')]
#[Group('F-22')]
class RecipeInputValidationTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Solange der Defekt besteht, würde das Exception-Reporting in
        // storage/logs schreiben – in der Testsuite unerwünscht.
        config(['logging.default' => 'null']);
    }

    public function test_array_search_parameter_does_not_cause_server_error(): void
    {
        $status = $this->get('/rezepte?search[]=a')->getStatusCode();

        $this->assertContains($status, [200, 302, 422], "Status $status");
    }

    public function test_unknown_rating_dimension_is_a_validation_error(): void
    {
        $this->actingAsHost();

        $response = $this->from(route('recipes.create'))
            ->post(route('recipes.store'), $this->recipePayload(['ratings' => [999999 => 3]]));

        $this->assertNotSame(500, $response->getStatusCode(), 'Server-Fehler statt Validierung');
        $response->assertSessionHasErrors();
        $this->assertSame(0, Recipe::count());
    }
}
