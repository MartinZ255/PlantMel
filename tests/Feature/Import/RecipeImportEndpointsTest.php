<?php

namespace Tests\Feature\Import;

use App\Jobs\ImportTikTokRecipe;
use App\Models\RecipeImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Charakterisierung RecipeImportController (store/show).
 */
class RecipeImportEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ohne Fake würde die sync-Queue den Job sofort ausführen.
        Queue::fake();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validUrls(): array
    {
        return [
            'www' => ['https://www.tiktok.com/@example/video/7000000000000000001'],
            'vm' => ['https://vm.tiktok.com/ZMabcdef/'],
            'vt' => ['https://vt.tiktok.com/ZSabcdef/'],
            'm' => ['https://m.tiktok.com/v/7000000000000000001.html'],
            'ohne Subdomain' => ['https://tiktok.com/@example/video/1'],
            'Großschreibung' => ['HTTPS://WWW.TIKTOK.COM/@example/video/1'],
            // Ist-Zustand: http:// wird akzeptiert (Regex https?)
            'http' => ['http://www.tiktok.com/@example/video/1'],
        ];
    }

    #[DataProvider('validUrls')]
    public function test_store_accepts_tiktok_urls_and_dispatches_job(string $url): void
    {
        $host = $this->actingAsHost();

        $response = $this->postJson(route('recipeImports.store'), ['url' => $url]);

        $import = RecipeImport::sole();
        // Ist-Zustand: 200 (nicht 201), nur die ID
        $response->assertOk()->assertExactJson(['id' => $import->id]);

        $this->assertSame($host->id, $import->user_id);
        $this->assertSame($url, $import->url);
        $this->assertSame('pending', $import->status);
        $this->assertNull($import->source);
        $this->assertNull($import->error);
        $this->assertNull($import->result);

        Queue::assertPushed(ImportTikTokRecipe::class, 1);
        Queue::assertPushed(ImportTikTokRecipe::class, fn (ImportTikTokRecipe $job) => $job->import->is($import));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidUrls(): array
    {
        return [
            'anderer Host' => ['https://www.youtube.com/watch?v=abc'],
            'tiktok.com als Subdomain-Präfix' => ['https://tiktok.com.evil.tld/'],
            'tiktok.com im Query' => ['https://evil.tld/?tiktok.com/'],
            'unbekannte Subdomain' => ['https://evil.tiktok.com/@x/video/1'],
            'Option als URL' => ['-o/x'],
            'leer' => [''],
            'javascript' => ['javascript:alert(1)'],
            'ohne Pfad-Slash' => ['https://www.tiktok.com'],
            'Userinfo-Trick' => ['https://www.tiktok.com@evil.tld/'],
            'Array' => [['https://www.tiktok.com/@x/video/1']],
        ];
    }

    #[DataProvider('invalidUrls')]
    public function test_store_rejects_invalid_urls(mixed $url): void
    {
        $this->actingAsHost();

        $this->postJson(route('recipeImports.store'), ['url' => $url])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');

        $this->assertDatabaseCount('recipe_imports', 0);
        Queue::assertNothingPushed();
    }

    public function test_store_without_url_field(): void
    {
        $this->actingAsHost();

        $this->postJson(route('recipeImports.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');

        Queue::assertNothingPushed();
    }

    public function test_store_regex_message_is_german(): void
    {
        $this->actingAsHost();

        $this->postJson(route('recipeImports.store'), ['url' => 'https://example.com/'])
            ->assertJsonValidationErrors(['url' => 'Bitte einen gültigen TikTok-Link einfügen.']);
    }

    public function test_show_returns_status_for_pending_import(): void
    {
        $host = $this->actingAsHost();
        $import = RecipeImport::factory()->for($host)->pending()->create();

        $this->getJson(route('recipeImports.show', $import))
            ->assertOk()
            ->assertExactJson([
                'id' => $import->id,
                'status' => 'pending',
                'source' => null,
                'error' => null,
            ]);
    }

    public function test_show_returns_status_for_done_import_without_result(): void
    {
        $host = $this->actingAsHost();
        $import = RecipeImport::factory()->for($host)->done('video')->create();

        // Das Ergebnis selbst wird nicht ausgeliefert (kommt über recipes.create?import=)
        $this->getJson(route('recipeImports.show', $import))
            ->assertOk()
            ->assertExactJson([
                'id' => $import->id,
                'status' => 'done',
                'source' => 'video',
                'error' => null,
            ]);
    }

    public function test_show_returns_error_for_failed_import(): void
    {
        $host = $this->actingAsHost();
        $import = RecipeImport::factory()->for($host)->failed('Kaputt')->create();

        $this->getJson(route('recipeImports.show', $import))
            ->assertOk()
            ->assertExactJson([
                'id' => $import->id,
                'status' => 'failed',
                'source' => null,
                'error' => 'Kaputt',
            ]);
    }

    public function test_show_of_foreign_import_is_forbidden(): void
    {
        $owner = User::factory()->host()->create();
        $import = RecipeImport::factory()->for($owner)->failed('Geheimer Pfad')->create();

        $this->actingAsHost();

        $this->getJson(route('recipeImports.show', $import))
            ->assertForbidden()
            ->assertJsonMissing(['error' => 'Geheimer Pfad']);
    }

    public function test_show_of_unknown_import_returns_404(): void
    {
        $this->actingAsHost();

        $this->getJson(route('recipeImports.show', 999999))->assertNotFound();
    }

    public function test_imports_are_deleted_with_their_user(): void
    {
        $owner = User::factory()->host()->create();
        $import = RecipeImport::factory()->for($owner)->create();

        $owner->delete();

        $this->assertModelMissing($import);
    }
}
