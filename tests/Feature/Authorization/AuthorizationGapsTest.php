<?php

namespace Tests\Feature\Authorization;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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

    /**
     * F-45: Host-Gruppe (routes/web.php:103) hat kein 'verified'.
     * (Wegen F-08 waere 'verified' derzeit ohnehin wirkungslos.)
     */
    public function test_f45_unverified_host_reaches_host_routes(): void
    {
        $this->assertNotContains('verified', Route::getRoutes()->getByName('recipes.create')->gatherMiddleware());

        $host = User::factory()->host()->unverified()->withoutTwoFactor()->create();

        $this->actingAs($host)
            ->get(route('recipes.create'))
            ->assertOk();
    }

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

    /**
     * F-08: User implementiert MustVerifyEmail nicht, 'verified' laesst alle durch.
     * Sollverhalten: tests/KnownDefects/Security/EmailVerificationEnforcementTest.php.
     */
    public function test_f08_unverified_user_reaches_home(): void
    {
        $user = User::factory()->unverified()->withoutTwoFactor()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk();
    }
}
