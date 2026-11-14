<?php

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * F-20 (Slice 3): Die Hell/Dunkel-Umschaltung ist abgeschaltet, die App rendert
 * immer hell (Entscheidung des Auftraggebers).
 */
#[Group('F-20')]
class AppearanceRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_appearance_route_is_not_registered(): void
    {
        $this->assertFalse(Route::has('appearance.edit'));

        $this->actingAsUser();
        $this->get('/settings/appearance')->assertNotFound();
    }

    public function test_html_never_gets_dark_class_even_with_old_cookie(): void
    {
        $response = $this->withUnencryptedCookie('appearance', 'dark')
            ->get(route('landing'))
            ->assertOk();

        $html = (string) $response->getContent();
        $this->assertMatchesRegularExpression('/<html lang="de">/', $html);
        $this->assertStringNotContainsString('class="dark"', $html);
        $this->assertStringNotContainsString('prefers-color-scheme', $html);
        $this->assertStringNotContainsString("classList.add('dark')", $html);
    }

    public function test_appearance_is_no_longer_shared_with_views(): void
    {
        $this->get(route('landing'))->assertOk();

        $this->assertArrayNotHasKey('appearance', View::getShared());
    }
}
