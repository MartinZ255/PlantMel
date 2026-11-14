<?php

namespace Tests\Feature\Errors;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

/**
 * F-68 (Slice 3): Fehler werden fuer Nicht-JSON-Requests als Inertia-Seite
 * `Error` mit den Props `status` (int) und `message` (deutscher Kurztext)
 * gerendert (bootstrap/app.php, withExceptions). JSON bleibt JSON, 419 wird
 * zum Redirect zurueck mit Flash-`error`, lokal mit APP_DEBUG=true bleibt die
 * Debug-Seite.
 *
 * phpunit.xml setzt APP_ENV=testing und APP_DEBUG=false.
 */
#[Group('F-68')]
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function statusCodes(): array
    {
        return [
            '403' => [403, 'Du hast keine Berechtigung für diese Seite.'],
            '404' => [404, 'Die angeforderte Seite wurde nicht gefunden.'],
            '429' => [429, 'Zu viele Anfragen. Bitte warte einen Moment und versuche es dann erneut.'],
            '500' => [500, 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuche es später erneut.'],
            '503' => [503, 'PlantMel wird gerade gewartet. Bitte versuche es in Kürze erneut.'],
        ];
    }

    #[DataProvider('statusCodes')]
    public function test_status_is_rendered_as_inertia_error_page(int $status, string $message): void
    {
        Route::middleware('web')->get('/_test/abort', fn () => abort($status));

        $this->get('/_test/abort')
            ->assertStatus($status)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', $status)
                ->where('message', $message)
                ->has('auth')
                ->has('flash')
            );
    }

    public function test_unknown_url_renders_error_page_with_shared_props(): void
    {
        $this->get('/diese-seite-gibt-es-nicht')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 404)
                ->where('message', 'Die angeforderte Seite wurde nicht gefunden.')
                // Ohne passende Route lief keine web-Middleware: Props werden nachgeholt.
                ->where('auth.user', null)
                ->where('flash', ['success' => null, 'error' => null])
                ->has('name')
            );
    }

    public function test_missing_model_renders_german_404_without_internal_details(): void
    {
        $response = $this->get(route('recipes.show', ['recipe' => 999999]));

        $response->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('message', 'Die angeforderte Seite wurde nicht gefunden.')
            );
        $response->assertDontSee('No query results');
    }

    public function test_403_without_message_uses_german_default(): void
    {
        $this->actingAsUser();
        $foreign = Collection::factory()->for(User::factory())->create();

        $this->get(route('collections.show', $foreign))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 403)
                ->where('message', 'Du hast keine Berechtigung für diese Seite.')
            );
    }

    public function test_403_with_message_shows_the_exception_message(): void
    {
        $this->actingAsUser();

        $this->get(route('recipes.create'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->where('message', 'Nur Hosts dürfen diese Aktion ausführen.')
            );
    }

    public function test_inertia_xhr_request_gets_inertia_json_error_page(): void
    {
        $this->actingAsUser();
        $foreign = Collection::factory()->for(User::factory())->create();

        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get(route('collections.show', $foreign))
            ->assertForbidden()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Error')
            ->assertJsonPath('props.status', 403);
    }

    public function test_500_hides_the_exception_message(): void
    {
        Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('geheime-interna'));

        $response = $this->get('/_test/boom');

        $response->assertInternalServerError()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 500)
            );
        $response->assertDontSee('geheime-interna');
    }

    public function test_json_requests_keep_json_errors(): void
    {
        $this->getJson('/diese-seite-gibt-es-nicht')
            ->assertNotFound()
            ->assertHeaderMissing('X-Inertia')
            ->assertJsonStructure(['message']);

        Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('geheime-interna'));

        $this->getJson('/_test/boom')
            ->assertInternalServerError()
            ->assertJsonStructure(['message'])
            ->assertDontSee('geheime-interna');
    }

    public function test_login_throttle_renders_429_error_page(): void
    {
        $user = User::factory()->create();
        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'falsch'])
            ->assertTooManyRequests()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 429)
            );
    }

    public function test_419_redirects_back_with_flash_error(): void
    {
        Route::middleware('web')->post('/_test/expired', fn () => abort(419));

        $this->actingAsUser();

        $this->from(route('collections.index'))
            ->post('/_test/expired')
            ->assertRedirect(route('collections.index'))
            ->assertSessionHas('error', 'Die Sitzung ist abgelaufen. Bitte versuche es erneut.');

        $this->get(route('collections.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.error', 'Die Sitzung ist abgelaufen. Bitte versuche es erneut.')
            );
    }

    public function test_419_inertia_put_becomes_303(): void
    {
        Route::middleware('web')->put('/_test/expired', fn () => abort(419));

        $this->actingAsUser();

        $this->from(route('profile.edit'))
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->put('/_test/expired')
            ->assertStatus(303)
            ->assertRedirect(route('profile.edit'));
    }

    public function test_419_json_stays_json(): void
    {
        Route::middleware('web')->post('/_test/expired', fn () => abort(419));

        $this->postJson('/_test/expired')
            ->assertStatus(419)
            ->assertJsonStructure(['message']);
    }

    public function test_error_page_carries_security_headers(): void
    {
        $this->get('/diese-seite-gibt-es-nicht')
            ->assertNotFound()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
    }

    public function test_local_debug_mode_keeps_the_framework_debug_page(): void
    {
        $this->app['env'] = 'local';
        config(['app.debug' => true]);

        $response = $this->get('/diese-seite-gibt-es-nicht');

        // Laravels eigene Fehlerseite (errors::404), nicht die Inertia-Root-View.
        $response->assertNotFound()->assertSee('Not Found');
        $this->assertStringNotContainsString('data-page', (string) $response->getContent());

        // 500: Debug-Seite mit Exception-Text statt der Inertia-Fehlerseite.
        Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('geheime-interna'));

        $this->get('/_test/boom')
            ->assertInternalServerError()
            ->assertSee('geheime-interna');
    }

    public function test_debug_mode_outside_local_still_renders_error_page(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => true]);
        Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('geheime-interna'));

        $this->get('/_test/boom')
            ->assertInternalServerError()
            ->assertInertia(fn (Assert $page) => $page->component('Error'));
    }
}
