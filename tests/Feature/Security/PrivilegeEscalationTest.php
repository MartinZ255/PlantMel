<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rechteausweitung ueber is_host (Bezug F-46): Obwohl is_host in User::$fillable
 * steht, setzen Registrierung (CreateNewUser.php:33-38) und Profil-Update
 * (ProfileController.php:30-41 mit ProfileUpdateRequest) das Feld nicht aus
 * Nutzereingaben. Diese Tests sichern das ab.
 */
class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_newly_registered_user_is_not_a_host(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Neue Person',
            'email' => 'neu@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'neu@example.com')->firstOrFail();
        $this->assertFalse($user->is_host);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_ignores_injected_is_host_flag(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Angreifer',
            'email' => 'angreifer@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_host' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertFalse(User::where('email', 'angreifer@example.com')->firstOrFail()->is_host);
    }

    public function test_profile_update_ignores_injected_is_host_flag(): void
    {
        $user = $this->actingAsUser();

        $this->patch(route('profile.update'), [
            'name' => 'Neuer Name',
            'email' => $user->email,
            'is_host' => '1',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('Neuer Name', $user->name);
        $this->assertFalse($user->is_host);
    }

    public function test_promoted_non_host_cannot_use_host_route_after_profile_update(): void
    {
        $user = $this->actingAsUser();

        $this->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'is_host' => '1',
        ]);

        $this->get(route('recipes.create'))->assertForbidden();
    }
}
