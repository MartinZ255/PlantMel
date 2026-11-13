<?php

namespace Tests\KnownDefects\Security;

use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Bekannter Defekt F-46: is_host steht in User::$fillable (app/Models/User.php:24).
 * Heute nicht ausnutzbar (Registrierung und Profil-Update setzen das Feld nicht
 * aus Nutzereingaben, siehe tests/Feature/Security/PrivilegeEscalationTest.php),
 * aber jeder kuenftige fill($request->all()) wuerde eine Rechteausweitung erlauben.
 *
 * Sollverhalten: is_host ist nicht mass-assignable; Hosts werden gezielt
 * (z. B. per Artisan-Command) ernannt.
 */
#[Group('known-defect')]
#[Group('F-46')]
class HostFlagMassAssignmentTest extends TestCase
{
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
}
