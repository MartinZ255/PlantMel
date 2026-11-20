<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regressionstest zu F-46 (behoben in Slice 1b): is_host stand in
 * User::$fillable. Jeder kuenftige fill($request->all()) haette eine
 * Rechteausweitung erlaubt.
 *
 * Sollverhalten: is_host ist nicht mass-assignable; Hosts werden gezielt per
 * `php artisan user:make-host` ernannt (siehe tests/Feature/Console/MakeHostCommandTest.php).
 * Factories (UserFactory::host()) und Seeder (forceFill) funktionieren weiter.
 */
#[Group('F-46')]
class HostFlagMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_host_is_not_fillable(): void
    {
        $this->assertFalse((new User)->isFillable('is_host'));
    }

    public function test_mass_assignment_does_not_set_is_host(): void
    {
        $user = (new User)->fill([
            'name' => 'Jemand',
            'email' => 'jemand@example.com',
            'is_host' => true,
        ]);

        $this->assertNotTrue($user->is_host);
    }

    public function test_user_create_ignores_is_host_and_uses_column_default(): void
    {
        $user = User::create([
            'name' => 'Jemand',
            'email' => 'jemand@example.com',
            'password' => 'password',
            'is_host' => true,
        ]);

        $this->assertFalse($user->fresh()->is_host);
    }

    public function test_factory_host_state_still_sets_flag(): void
    {
        $this->assertTrue(User::factory()->host()->create()->fresh()->is_host);
        $this->assertTrue(User::factory()->create(['is_host' => true])->fresh()->is_host);
        $this->assertFalse(User::factory()->create()->fresh()->is_host);
    }

    public function test_acting_as_host_helper_still_grants_host_routes(): void
    {
        $host = $this->actingAsHost();

        $this->assertTrue($host->fresh()->is_host);
        $this->get(route('recipes.create'))->assertOk();
    }
}
