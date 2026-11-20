<?php

namespace Tests\Feature\Authorization;

use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Ist-Zustand zu offenen Befunden (gruen, solange der Befund besteht).
 *
 * Diese Tests dokumentieren heutiges Verhalten, KEIN Sollverhalten. Wird ein
 * Befund behoben, schlagen sie bewusst fehl und muessen angepasst bzw. durch
 * den zugehoerigen Test aus tests/KnownDefects ersetzt werden.
 */
class AuthorizationGapsTest extends TestCase
{
    use RefreshDatabase;

    // Slice 4 (F-08/E2): test_f45_unverified_host_reaches_host_routes und
    // test_f08_unverified_user_reaches_home entfallen. Die Host-Gruppe traegt
    // jetzt 'verified'; das Verhalten in beiden Schalterstellungen pruefen
    // tests/Feature/Auth/EmailVerificationEnforcementTest.php (AN) und
    // tests/Feature/Auth/EmailVerificationDisabledTest.php (AUS, Default).

    /**
     * F-45 (Designentscheidung E7 offen): Rezepte haben keinen Besitzer, jeder
     * Host darf jedes Rezept loeschen.
     */
    public function test_f45_any_host_can_delete_any_recipe_because_recipes_have_no_owner(): void
    {
        $this->assertFalse(Schema::hasColumn('recipes', 'user_id'));

        $recipe = Recipe::factory()->create();
        $this->actingAsHost();

        $this->delete(route('recipes.delete', $recipe))
            ->assertRedirect(route('recipes.index'));

        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
    }
}
