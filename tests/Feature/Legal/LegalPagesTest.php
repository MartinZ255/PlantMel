<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Slice 4 (F-10, F-58): Impressum und Datenschutzerklaerung als Vorlagen.
 *
 * Die Werte kommen aus config/legal.php; `filled` wird in routes/web.php
 * berechnet (false, solange ein Pflichtwert leer ist oder "[" enthaelt).
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    /** Pflichtwerte mit gueltigen (synthetischen) Angaben. */
    private const FILLED = [
        'legal.operator_name' => 'Erika Musterfrau',
        'legal.operator_street' => 'Musterweg 1',
        'legal.operator_postal_city' => '12345 Musterstadt',
        'legal.operator_country' => 'Deutschland',
        'legal.operator_email' => 'kontakt@example.test',
        'legal.hosting_provider_name' => 'Beispiel Hosting GmbH',
        'legal.hosting_provider_address' => 'Serverstraße 2, 54321 Rechenstadt',
        'legal.server_log_retention' => '14 Tage',
        'legal.mail_provider' => 'Beispiel Mail GmbH, Postweg 3, 11111 Briefstadt',
        'legal.last_updated' => '14.11.2026',
    ];

    /** Platzhalter wie in config/legal.php (deterministisch, unabhaengig von einer .env). */
    private const PLACEHOLDERS = [
        'legal.operator_name' => '[VOR- UND NACHNAME]',
        'legal.operator_street' => '[STRASSE UND HAUSNUMMER]',
        'legal.operator_postal_city' => '[PLZ UND ORT]',
        'legal.operator_country' => 'Deutschland',
        'legal.operator_email' => '[E-MAIL-ADRESSE]',
        'legal.operator_phone' => null,
        'legal.content_responsible_name' => null,
        'legal.content_responsible_address' => null,
        'legal.hosting_provider_name' => '[NAME DES HOSTING-ANBIETERS]',
        'legal.hosting_provider_address' => '[ANSCHRIFT DES HOSTING-ANBIETERS]',
        'legal.server_log_retention' => '[SPEICHERDAUER DER SERVER-LOGFILES, z. B. 14 Tage]',
        'legal.mail_provider' => '[MAILANBIETER BZW. EIGENER MAILSERVER MIT ANSCHRIFT]',
        'legal.last_updated' => '[TT.MM.JJJJ]',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(self::PLACEHOLDERS);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function legalRoutes(): array
    {
        return [
            'Impressum' => ['legal.imprint', 'legal/Impressum'],
            'Datenschutz' => ['legal.privacy', 'legal/Datenschutz'],
        ];
    }

    public function test_routes_have_the_expected_uris(): void
    {
        $this->assertSame('/impressum', route('legal.imprint', [], false));
        $this->assertSame('/datenschutz', route('legal.privacy', [], false));
    }

    #[DataProvider('legalRoutes')]
    public function test_page_is_public_and_renders_component_with_legal_prop(string $route, string $component): void
    {
        $this->get(route($route))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->where('legal.operator_name', '[VOR- UND NACHNAME]')
                ->where('legal.operator_email', '[E-MAIL-ADRESSE]')
                ->where('legal.last_updated', '[TT.MM.JJJJ]')
                ->where('legal.filled', false)
                ->etc());
    }

    #[DataProvider('legalRoutes')]
    public function test_page_is_reachable_for_signed_in_user(string $route, string $component): void
    {
        $this->actingAsUser();

        $this->get(route($route))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)->has('legal'));
    }

    #[DataProvider('legalRoutes')]
    public function test_filled_is_true_when_all_required_values_are_set(string $route, string $component): void
    {
        config(self::FILLED);

        $this->get(route($route))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->where('legal.filled', true)
                ->where('legal.operator_name', 'Erika Musterfrau')
                ->where('legal.operator_phone', null)
                ->etc());
    }

    public function test_filled_is_false_while_a_single_placeholder_remains(): void
    {
        config(self::FILLED);
        config(['legal.mail_provider' => '[MAILANBIETER BZW. EIGENER MAILSERVER MIT ANSCHRIFT]']);

        $this->get(route('legal.privacy'))
            ->assertInertia(fn (Assert $page) => $page->where('legal.filled', false)->etc());
    }

    public function test_filled_is_false_when_a_required_value_is_empty(): void
    {
        config(self::FILLED);
        config(['legal.operator_email' => '   ']);

        $this->get(route('legal.imprint'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('legal.filled', false)
                ->where('legal.operator_email', null)
                ->etc());
    }

    public function test_optional_values_are_checked_for_placeholders_when_set(): void
    {
        config(self::FILLED);
        config(['legal.operator_phone' => '[TELEFON]']);

        $this->get(route('legal.imprint'))
            ->assertInertia(fn (Assert $page) => $page->where('legal.filled', false)->etc());

        config(['legal.operator_phone' => '']);

        $this->get(route('legal.imprint'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('legal.filled', true)
                ->where('legal.operator_phone', null)
                ->etc());
    }

    public function test_config_file_ships_unmistakable_placeholders(): void
    {
        $source = (string) file_get_contents(config_path('legal.php'));

        foreach (self::PLACEHOLDERS as $key => $value) {
            if (is_string($value) && str_contains($value, '[')) {
                $this->assertStringContainsString("'{$value}'", $source, $key);
            }
        }
    }

    public function test_privacy_page_receives_session_cookie_details_from_config(): void
    {
        config([
            'session.cookie' => 'plantmel-session',
            'session.lifetime' => 120,
            'session.driver' => 'database',
        ]);

        $this->get(route('legal.privacy'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('session.cookie', 'plantmel-session')
                ->where('session.lifetime_minutes', 120)
                ->where('session.database', true)
                ->etc());
    }

    #[DataProvider('legalRoutes')]
    public function test_rendered_html_does_not_reference_bunny_fonts(string $route): void
    {
        $html = (string) $this->get(route($route))->assertOk()->getContent();

        $this->assertStringNotContainsString('fonts.bunny.net', $html);
    }

    public function test_landing_html_does_not_reference_bunny_fonts(): void
    {
        $html = (string) $this->get(route('landing'))->assertOk()->getContent();

        $this->assertStringNotContainsString('fonts.bunny.net', $html);
    }
}
