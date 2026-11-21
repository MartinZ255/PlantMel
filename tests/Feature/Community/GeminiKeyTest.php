<?php

namespace Tests\Feature\Community;

use App\Http\Controllers\RecipeImportController;
use App\Http\Controllers\Settings\AiImportController;
use App\Jobs\ImportTikTokRecipe;
use App\Models\RecipeImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Community\Concerns\CreatesCommunityRecipes;
use Tests\Feature\Import\Concerns\FakesTikTokImport;
use Tests\TestCase;

/**
 * E18: eigener Gemini-Schlüssel – verschlüsselt, maskiert, nie im Log,
 * der Job nutzt den Schlüssel des auslösenden Nutzers, der Server-Schlüssel
 * gilt nur für Hosts. Keine echten Gemini-Aufrufe (Http::fake).
 */
class GeminiKeyTest extends TestCase
{
    use CreatesCommunityRecipes;
    use FakesTikTokImport;
    use RefreshDatabase;

    private const SERVER_KEY = 'server-key-geheim-1234';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureImporter(self::SERVER_KEY);
    }

    protected function tearDown(): void
    {
        $this->cleanupImportTempFiles();

        parent::tearDown();
    }

    public function test_key_is_stored_encrypted_and_only_masked_in_props(): void
    {
        $user = $this->actingAsUser();
        $key = $this->geminiKey('wxyz');

        $this->put(route('settings.ai.update'), ['gemini_api_key' => $key])
            ->assertRedirect(route('settings.ai.edit'))
            ->assertSessionHas('success');

        $raw = DB::table('users')->where('id', $user->id)->value('gemini_api_key');
        $this->assertNotSame($key, $raw);
        $this->assertStringNotContainsString($key, (string) $raw);
        $this->assertSame($key, $user->fresh()->gemini_api_key);

        $response = $this->get(route('settings.ai.edit'));
        $response->assertInertia(fn (Assert $page) => $page
            ->component('settings/AiImport')
            ->where('hasKey', true)
            ->where('keyHint', 'wxyz'));
        $this->assertStringNotContainsString($key, $response->getContent());

        // auch nicht über Serialisierung des Modells
        $this->assertArrayNotHasKey('gemini_api_key', $user->fresh()->toArray());
        $this->assertStringNotContainsString($key, $this->get(route('home'))->getContent());
    }

    public function test_format_is_checked_and_invalid_key_is_not_flashed(): void
    {
        $user = $this->actingAsUser();

        foreach (['', 'abc', 'AIza'.str_repeat('x', 10), 'XXza'.str_repeat('x', 35), 'AIza'.str_repeat('x', 34).'!'] as $bad) {
            $this->from(route('settings.ai.edit'))
                ->put(route('settings.ai.update'), ['gemini_api_key' => $bad])
                ->assertSessionHasErrors('gemini_api_key')
                ->assertSessionMissing('_old_input.gemini_api_key');
        }

        $this->assertNull($user->fresh()->gemini_api_key);
    }

    public function test_key_can_be_deleted(): void
    {
        $user = $this->actingAsUser();
        $user->forceFill(['gemini_api_key' => $this->geminiKey()])->save();

        $this->delete(route('settings.ai.destroy'))->assertSessionHas('success');

        $this->assertNull(DB::table('users')->where('id', $user->id)->value('gemini_api_key'));
    }

    public function test_check_button_uses_faked_gemini_call(): void
    {
        $user = $this->actingAsUser();
        $key = $this->geminiKey();
        $user->forceFill(['gemini_api_key' => $key])->save();

        Http::fake([AiImportController::CHECK_URL => Http::sequence()
            ->push(['models' => []])
            ->push(['error' => 'invalid'], 400)]);
        $this->post(route('settings.ai.check'))->assertSessionHas('success', 'Der Schlüssel funktioniert.');
        Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', $key)
            && ! str_contains($request->url(), $key));

        $this->post(route('settings.ai.check'))->assertSessionHas('error');
    }

    public function test_non_host_without_key_cannot_import_and_never_uses_server_key(): void
    {
        Queue::fake();
        $this->actingAsUser();

        $this->postJson(route('communityImports.store'), ['url' => self::URL])
            ->assertStatus(422)
            ->assertJson(['message' => RecipeImportController::MESSAGE_NO_USER_KEY]);

        Queue::assertNothingPushed();
        $this->assertSame(0, RecipeImport::count());
    }

    public function test_job_uses_the_users_own_key_and_payload_contains_no_key(): void
    {
        $user = User::factory()->create();
        $key = $this->geminiKey('u5er');
        $user->forceFill(['gemini_api_key' => $key])->save();

        Queue::fake();
        $this->actingAs($user)->postJson(route('communityImports.store'), ['url' => self::URL])->assertOk();
        Queue::assertPushed(ImportTikTokRecipe::class, function (ImportTikTokRecipe $job) use ($key) {
            return ! str_contains(serialize($job), $key) && ! str_contains(serialize($job), self::SERVER_KEY);
        });

        $import = RecipeImport::firstOrFail();
        Process::fake(['*' => Process::result(output: json_encode(['description' => self::LONG_CAPTION]))]);
        Http::fake(fn () => Http::response($this->geminiText([
            'is_complete' => true, 'name' => 'X', 'ingredients' => [['name' => 'Salz']], 'steps' => ['Kochen'],
        ])));

        (new ImportTikTokRecipe($import))->handle();

        $this->assertSame('done', $import->fresh()->status);
        Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', $key));
        Http::assertNotSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', self::SERVER_KEY));
    }

    public function test_host_job_uses_server_key(): void
    {
        $host = User::factory()->host()->create();
        $host->forceFill(['gemini_api_key' => $this->geminiKey()])->save();
        $import = RecipeImport::factory()->pending()->create(['user_id' => $host->id, 'url' => self::URL]);

        Process::fake(['*' => Process::result(output: json_encode(['description' => self::LONG_CAPTION]))]);
        Http::fake(fn () => Http::response($this->geminiText([
            'is_complete' => true, 'name' => 'X', 'ingredients' => [['name' => 'Salz']], 'steps' => ['Kochen'],
        ])));

        (new ImportTikTokRecipe($import))->handle();

        Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', self::SERVER_KEY));
    }

    public function test_job_without_user_key_fails_without_any_request(): void
    {
        // z. B. Schlüssel nach dem Start gelöscht
        $user = User::factory()->create();
        $import = RecipeImport::factory()->pending()->create(['user_id' => $user->id, 'url' => self::URL]);
        Http::fake();

        try {
            (new ImportTikTokRecipe($import))->handle();
            $this->fail('Job hätte scheitern müssen.');
        } catch (\RuntimeException) {
        }

        $this->assertSame('failed', $import->fresh()->status);
        $this->assertSame(ImportTikTokRecipe::MESSAGE_NO_USER_KEY, $import->fresh()->error);
        Http::assertNothingSent();
    }

    public function test_user_key_never_reaches_the_log(): void
    {
        $user = User::factory()->create();
        $key = $this->geminiKey('l0gs');
        $user->forceFill(['gemini_api_key' => $key])->save();
        $import = RecipeImport::factory()->pending()->create(['user_id' => $user->id, 'url' => self::URL]);

        Process::fake(['*' => Process::result(output: json_encode(['description' => self::LONG_CAPTION]))]);
        // Gemini-Fehler, der den Schlüssel zurückspiegelt
        Http::fake(fn () => Http::response('{"error":"API key '.$key.' not valid"}', 400));

        $logged = [];
        Log::listen(function ($message) use (&$logged) {
            $logged[] = $message->message.' '.json_encode($message->context);
        });

        try {
            (new ImportTikTokRecipe($import))->handle();
        } catch (\Throwable) {
        }

        $this->assertNotEmpty($logged);
        foreach ($logged as $line) {
            $this->assertStringNotContainsString($key, $line);
        }
        $this->assertStringContainsString('[redacted]', implode("\n", $logged));
        $this->assertStringNotContainsString($key, (string) $import->fresh()->error);
    }
}
