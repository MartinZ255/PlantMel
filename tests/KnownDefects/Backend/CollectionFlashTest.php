<?php

namespace Tests\KnownDefects\Backend;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Bekannter Defekt F-17: Controller setzen Session-Flash (`->with('success', …)`),
 * HandleInertiaRequests::share() teilt aber kein `flash` – Meldungen kommen im
 * Frontend nie an.
 *
 * Sollverhalten: Nach `POST /collections` enthält die Folgeseite die geteilte
 * Inertia-Prop `flash.success`.
 */
#[Group('known-defect')]
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
            );
    }
}
