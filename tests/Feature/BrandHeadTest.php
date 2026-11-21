<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Logo-Vorbereitung (docs/modernization/LOGO.md): Kopfbereich der Blade-Vorlage und
 * Web-App-Manifest. Manifest-Link und og:image erscheinen erst, wenn
 * `npm run brand:icons` die Dateien in public/ erzeugt hat.
 */
class BrandHeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_contains_theme_color_and_existing_icons(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('<meta name="theme-color" content="#143D2D">', false);
        $response->assertSee('<link rel="icon" href="/favicon.ico" sizes="any">', false);
        $response->assertSee('<link rel="icon" href="/favicon.svg" type="image/svg+xml">', false);
        $response->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', false);
    }

    public function test_manifest_link_follows_generated_icons(): void
    {
        $response = $this->get(route('landing'));

        if (file_exists(public_path('icons/icon-192.png'))) {
            $response->assertSee('<link rel="manifest" href="/site.webmanifest">', false);
        } else {
            $response->assertDontSee('site.webmanifest', false);
        }
    }

    public function test_og_image_follows_generated_file(): void
    {
        $response = $this->get(route('landing'));

        if (file_exists(public_path('og-image.png'))) {
            $response->assertSee('<meta property="og:image" content="'.url('/og-image.png').'">', false);
        } else {
            $response->assertDontSee('og:image', false);
        }
    }

    public function test_web_manifest_is_valid(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(public_path('site.webmanifest')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('PlantMel', $manifest['name']);
        $this->assertSame('PlantMel', $manifest['short_name']);
        $this->assertSame('#143D2D', $manifest['theme_color']);
        $this->assertSame('#F2F5EA', $manifest['background_color']);
        $this->assertSame('/', $manifest['start_url']);

        $icons = collect($manifest['icons'])->keyBy('src');
        $this->assertSame('192x192', $icons['/icons/icon-192.png']['sizes']);
        $this->assertSame('512x512', $icons['/icons/icon-512.png']['sizes']);
        $this->assertSame('maskable', $icons['/icons/icon-512-maskable.png']['purpose']);
    }

    public function test_placeholder_marks_exist_for_the_icon_pipeline(): void
    {
        foreach (['logo-mark.svg', 'logo-mark-light.svg'] as $file) {
            $path = public_path('brand/placeholder/'.$file);
            $this->assertFileExists($path);
            $this->assertStringContainsString('viewBox="0 0 100 100"', (string) file_get_contents($path));
        }
    }
}
