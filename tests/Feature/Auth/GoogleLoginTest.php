<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Closure;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * E17 / ADR-004: Anmeldung mit Google.
 *
 * Kein echter Aufruf bei Google: Der Callback nutzt Socialite::fake(); der
 * Redirect-Test baut nur die Autorisierungs-URL (ohne HTTP-Anfrage), und
 * TestCase::setUp() verbietet ungefakte HTTP-Anfragen.
 */
class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private const GOOGLE_ID = '109876543210987654321';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureGoogle();
    }

    // ------------------------------------------------------------------
    // Konfiguration fehlt
    // ------------------------------------------------------------------

    public function test_redirect_without_configuration_returns_to_login_with_notice(): void
    {
        $this->configureGoogle(null, null);

        $this->get(route('auth.google.redirect'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Die Anmeldung mit Google ist derzeit nicht verfügbar. Bitte melde dich mit E-Mail und Passwort an.');

        $this->assertGuest();
    }

    public function test_callback_without_configuration_returns_to_login_with_notice(): void
    {
        $this->configureGoogle('only-id', null);
        $this->fakeGoogle(fn () => $this->fail('Socialite darf ohne Konfiguration nicht aufgerufen werden.'));

        $this->get(route('auth.google.callback', ['code' => 'x', 'state' => 'y']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_login_page_shows_flash_error_after_redirect(): void
    {
        $this->configureGoogle(null, null);

        $this->followingRedirects()
            ->get(route('auth.google.redirect'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/Login')
                ->where('flash.error', 'Die Anmeldung mit Google ist derzeit nicht verfügbar. Bitte melde dich mit E-Mail und Passwort an.')
                ->where('features.googleLogin', false)
            );
    }

    // ------------------------------------------------------------------
    // Weiterleitung zu Google
    // ------------------------------------------------------------------

    public function test_redirect_points_to_google_with_minimal_scopes_and_state(): void
    {
        $response = $this->get(route('auth.google.redirect'));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('test-client-id', $query['client_id']);
        $this->assertSame('http://localhost/auth/google/callback', $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame(['openid', 'email', 'profile'], explode(' ', $query['scope']));
        $this->assertNotEmpty($query['state']);
        $this->assertSame($query['state'], session('state'));
        $this->assertFalse(session('google_login.remember'));
    }

    public function test_redirect_remembers_the_remember_me_choice(): void
    {
        $this->get(route('auth.google.redirect', ['remember' => 1]))->assertRedirect();

        $this->assertTrue(session('google_login.remember'));
    }

    // ------------------------------------------------------------------
    // Callback: neue Registrierung
    // ------------------------------------------------------------------

    public function test_new_google_user_is_registered_without_password_and_without_host(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 21)->setTime(9, 0));
        $this->fakeGoogle($this->googleUser(['email' => 'Neu.Nutzer@Example.TEST', 'name' => 'Neu Nutzer']));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('home'));
        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Neu Nutzer', $user->name);
        $this->assertSame('neu.nutzer@example.test', $user->email);
        $this->assertSame(self::GOOGLE_ID, $user->google_id);
        $this->assertNull($user->password);
        $this->assertFalse($user->is_host);
        $this->assertSame('2026-11-21 09:00:00', $user->email_verified_at->format('Y-m-d H:i:s'));
        $this->assertFalse($user->hasPassword());
    }

    public function test_new_google_user_without_name_gets_local_part_of_email(): void
    {
        $this->fakeGoogle($this->googleUser(['email' => 'kraeuter@example.test', 'name' => null]));

        $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

        $this->assertSame('kraeuter', User::sole()->name);
    }

    public function test_google_id_is_hidden_from_serialization(): void
    {
        $this->fakeGoogle($this->googleUser());
        $this->get(route('auth.google.callback'));

        $this->assertArrayNotHasKey('google_id', User::sole()->toArray());
        $this->assertNotContains('google_id', (new User)->getFillable());
    }

    // ------------------------------------------------------------------
    // Callback: Verknüpfung und bestehende Konten
    // ------------------------------------------------------------------

    public function test_existing_account_is_linked_by_email_case_insensitively(): void
    {
        $existing = User::factory()->create(['email' => 'erika@example.test']);
        // Altbestand mit Großbuchstaben (vor lowercase_usernames möglich)
        $existing->forceFill(['email' => 'Erika@Example.TEST'])->save();
        $passwordHash = $existing->password;

        $this->fakeGoogle($this->googleUser(['email' => 'erika@EXAMPLE.test']));

        $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

        $this->assertSame(1, User::count());
        $existing->refresh();
        $this->assertAuthenticatedAs($existing);
        $this->assertSame(self::GOOGLE_ID, $existing->google_id);
        $this->assertSame('Erika@Example.TEST', $existing->email, 'E-Mail bleibt unverändert.');
        $this->assertSame($passwordHash, $existing->password, 'Passwort bleibt erhalten.');
    }

    public function test_linking_keeps_host_status(): void
    {
        $host = User::factory()->host()->create(['email' => 'host@example.test']);
        $this->fakeGoogle($this->googleUser(['email' => 'host@example.test']));

        $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($host);
        $this->assertTrue($host->refresh()->is_host);
        $this->assertSame(self::GOOGLE_ID, $host->google_id);
    }

    public function test_linking_sets_missing_verification_date_only(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 21)->setTime(10, 0));
        $unverified = User::factory()->unverified()->create(['email' => 'offen@example.test']);
        $verified = User::factory()->create([
            'email' => 'fertig@example.test',
            'email_verified_at' => now()->subYear(),
        ]);

        $this->fakeGoogle($this->googleUser(['email' => 'offen@example.test', 'id' => 'google-a']));
        $this->get(route('auth.google.callback'));
        Auth::logout();
        $this->fakeGoogle($this->googleUser(['email' => 'fertig@example.test', 'id' => 'google-b']));
        $this->get(route('auth.google.callback'));

        $this->assertSame('2026-11-21 10:00:00', $unverified->refresh()->email_verified_at->format('Y-m-d H:i:s'));
        $this->assertSame('2025-11-21 10:00:00', $verified->refresh()->email_verified_at->format('Y-m-d H:i:s'));
    }

    public function test_existing_google_id_logs_in_even_if_email_changed(): void
    {
        $user = User::factory()->create(['email' => 'neue-adresse@example.test']);
        $user->forceFill(['google_id' => self::GOOGLE_ID])->save();
        User::factory()->create(['email' => 'google-adresse@example.test']);

        $this->fakeGoogle($this->googleUser(['email' => 'google-adresse@example.test']));

        $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(2, User::count());
        $this->assertSame('neue-adresse@example.test', $user->refresh()->email);
    }

    public function test_account_linked_to_another_google_account_is_not_relinked(): void
    {
        $user = User::factory()->create(['email' => 'erika@example.test']);
        $user->forceFill(['google_id' => 'anderes-google-konto'])->save();

        $this->fakeGoogle($this->googleUser(['email' => 'erika@example.test']));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Dein PlantMel-Konto ist bereits mit einem anderen Google-Konto verknüpft. Bitte melde dich mit diesem Google-Konto oder mit deinem Passwort an.');

        $this->assertGuest();
        $this->assertSame('anderes-google-konto', $user->refresh()->google_id);
    }

    public function test_callback_respects_intended_url(): void
    {
        $this->get(route('collections.index'))->assertRedirect(route('login'));
        $this->fakeGoogle($this->googleUser());

        $this->get(route('auth.google.callback'))->assertRedirect(route('collections.index'));
        $this->assertAuthenticated();
    }

    public function test_callback_regenerates_the_session(): void
    {
        $this->fakeGoogle($this->googleUser());
        $this->startSession();
        $before = session()->getId();

        $this->get(route('auth.google.callback'));

        $this->assertAuthenticated();
        $this->assertNotSame($before, session()->getId());
    }

    public function test_remember_me_is_applied_only_when_chosen(): void
    {
        $this->fakeGoogle($this->googleUser(['email' => 'ohne@example.test', 'id' => 'g-ohne']));
        $this->get(route('auth.google.redirect'));
        $this->get(route('auth.google.callback'))->assertCookieMissing(Auth::guard()->getRecallerName());
        $this->assertNull(User::where('google_id', 'g-ohne')->sole()->remember_token);
        Auth::logout();

        $this->fakeGoogle($this->googleUser(['email' => 'mit@example.test', 'id' => 'g-mit']));
        $this->get(route('auth.google.redirect', ['remember' => 1]));
        $this->get(route('auth.google.callback'))->assertCookie(Auth::guard()->getRecallerName());
        $this->assertNotNull(User::where('google_id', 'g-mit')->sole()->remember_token);
    }

    // ------------------------------------------------------------------
    // Callback: Ablehnung
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function verificationFlags(): array
    {
        return [
            'email_verified true' => [['email_verified' => true], true],
            'email_verified "true"' => [['email_verified' => 'true'], true],
            'verified_email true (v2)' => [['verified_email' => true], true],
            'email_verified false' => [['email_verified' => false], false],
            'email_verified "false"' => [['email_verified' => 'false'], false],
            'Angabe fehlt' => [[], false],
            'unbekannter Wert' => [['email_verified' => 'ja'], false],
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    #[DataProvider('verificationFlags')]
    public function test_only_verified_google_emails_are_accepted(array $raw, bool $accepted): void
    {
        Log::spy();
        $this->fakeGoogle($this->googleUser(['email' => 'pruefung@example.test'], $raw));

        $response = $this->get(route('auth.google.callback'));

        if ($accepted) {
            $response->assertRedirect(route('home'));
            $this->assertAuthenticated();

            return;
        }

        $response->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Deine E-Mail-Adresse ist bei Google nicht bestätigt. Bitte bestätige sie bei Google oder registriere dich mit E-Mail und Passwort.');
        $this->assertGuest();
        $this->assertSame(0, User::count());
        Log::shouldHaveReceived('warning')->once()->with('Google-Anmeldung fehlgeschlagen', ['reason' => 'email_not_verified']);
    }

    public function test_unverified_google_email_does_not_link_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'erika@example.test']);
        $this->fakeGoogle($this->googleUser(['email' => 'erika@example.test'], ['email_verified' => false]));

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($user->refresh()->google_id);
    }

    public function test_google_user_without_email_is_rejected(): void
    {
        $this->fakeGoogle($this->googleUser(['email' => null]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google hat keine verwendbare E-Mail-Adresse übermittelt. Bitte registriere dich mit E-Mail und Passwort.');

        $this->assertSame(0, User::count());
    }

    public function test_access_denied_returns_to_login_without_calling_google(): void
    {
        Log::spy();
        $this->fakeGoogle(fn () => $this->fail('Bei error=access_denied darf kein Token-Austausch stattfinden.'));

        $this->get(route('auth.google.callback', ['error' => 'access_denied', 'state' => 'x']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Die Anmeldung mit Google wurde abgebrochen.');

        $this->assertGuest();
        Log::shouldHaveReceived('warning')->once()->with('Google-Anmeldung fehlgeschlagen', ['reason' => 'access_denied']);
    }

    public function test_other_provider_error_returns_to_login(): void
    {
        $this->fakeGoogle(fn () => $this->fail('kein Token-Austausch'));

        $this->get(route('auth.google.callback', ['error' => 'server_error']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google hat die Anmeldung nicht bestätigt. Bitte versuche es erneut.');
    }

    public function test_invalid_state_returns_to_login(): void
    {
        Log::spy();
        $this->fakeGoogle(fn () => throw new InvalidStateException);

        $this->get(route('auth.google.callback', ['code' => 'abc', 'state' => 'falsch']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Die Anmeldung mit Google ist abgelaufen oder ungültig. Bitte versuche es erneut.');

        $this->assertGuest();
        Log::shouldHaveReceived('warning')->once()->with('Google-Anmeldung fehlgeschlagen', ['reason' => 'invalid_state']);
    }

    public function test_real_provider_rejects_missing_state_without_http_request(): void
    {
        // echter GoogleProvider: state in der Session fehlt -> InvalidStateException,
        // bevor ein Token-Austausch versucht wird (Http::preventStrayRequests greift
        // nur für den Laravel-HTTP-Client; Guzzle würde hier nie erreicht).
        $this->get(route('auth.google.callback', ['code' => 'abc', 'state' => 'falsch']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Die Anmeldung mit Google ist abgelaufen oder ungültig. Bitte versuche es erneut.');
    }

    public function test_provider_exception_returns_to_login_and_logs_no_personal_data(): void
    {
        Log::spy();
        $this->fakeGoogle(fn () => throw new ConnectException(
            'cURL error 6: Antwort enthält erika@example.test',
            new PsrRequest('POST', 'https://oauth2.googleapis.com/token'),
        ));

        $this->get(route('auth.google.callback', ['code' => 'abc', 'state' => 'x']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Die Anmeldung mit Google hat nicht geklappt. Bitte versuche es später erneut.');

        $this->assertGuest();
        Log::shouldHaveReceived('warning')->once()->with('Google-Anmeldung fehlgeschlagen', [
            'reason' => 'provider_exception',
            'exception' => ConnectException::class,
        ]);
    }

    // ------------------------------------------------------------------
    // Middleware
    // ------------------------------------------------------------------

    public function test_authenticated_user_is_redirected_home_by_guest_middleware(): void
    {
        $user = $this->actingAsUser();
        $this->fakeGoogle(fn () => $this->fail('Angemeldete Nutzer erreichen den Callback nicht.'));

        $this->get(route('auth.google.redirect'))->assertRedirect(route('home'));
        $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->refresh()->google_id);
    }

    public function test_google_routes_are_rate_limited(): void
    {
        $this->configureGoogle(null, null);

        for ($i = 0; $i < 10; $i++) {
            $this->get(route('auth.google.redirect'))->assertRedirect(route('login'));
        }

        $this->get(route('auth.google.redirect'))->assertTooManyRequests();
    }

    // ------------------------------------------------------------------
    // Helfer
    // ------------------------------------------------------------------

    private function configureGoogle(?string $id = 'test-client-id', ?string $secret = 'test-client-secret'): void
    {
        config([
            'services.google.client_id' => $id,
            'services.google.client_secret' => $secret,
            'services.google.redirect' => '/auth/google/callback',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $raw  zusätzliche Rohdaten; Standard: email_verified = true
     */
    private function googleUser(array $attributes = [], ?array $raw = null): SocialiteUser
    {
        $attributes = array_merge([
            'id' => self::GOOGLE_ID,
            'name' => 'Erika Muster',
            'email' => 'erika@example.test',
        ], $attributes);

        $user = SocialiteUser::fake($attributes);
        $user->setRaw(array_merge(
            ['sub' => $attributes['id'], 'email' => $attributes['email'], 'name' => $attributes['name']],
            $raw ?? ['email_verified' => true],
        ));

        return $user;
    }

    private function fakeGoogle(SocialiteUser|Closure $user): void
    {
        Socialite::fake('google', $user);
    }
}
