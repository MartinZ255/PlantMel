<?php

namespace Tests\Feature\Pages;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regressionstest zu F-17 (behoben in Slice 3, vorher
 * tests/KnownDefects/Backend/CollectionFlashTest.php): Controller setzen
 * Session-Flash (`->with('success', …)`), HandleInertiaRequests::share() teilt
 * es als Prop `flash` = ['success' => ?string, 'error' => ?string].
 */
#[Group('F-17')]
class CollectionFlashTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_flash_is_shared_with_following_page(): void
    {
        $this->actingAsUser();

        $this->post(route('collections.store'), ['name' => 'Neue Sammlung'])
            ->assertRedirect(route('collections.index'))
            ->assertSessionHas('success', 'Sammlung wurde erstellt.');

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('collections/Index')
                ->where('flash.success', 'Sammlung wurde erstellt.')
                ->where('flash.error', null)
            );
    }

    public function test_flash_is_gone_on_the_second_following_page(): void
    {
        $this->actingAsUser();

        $this->post(route('collections.store'), ['name' => 'Neue Sammlung']);
        $this->get(route('collections.index'))->assertOk();

        $this->get(route('collections.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash', ['success' => null, 'error' => null])
            );
    }

    public function test_error_flash_is_shared(): void
    {
        $this->actingAsUser();

        $this->withSession(['error' => 'Etwas ist schiefgelaufen.'])
            ->get(route('collections.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.error', 'Etwas ist schiefgelaufen.')
                ->where('flash.success', null)
            );
    }

    public function test_guest_pages_carry_empty_flash(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash', ['success' => null, 'error' => null])
            );
    }
}
