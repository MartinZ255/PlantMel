<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Die Framework-Route "storage.local.upload" (PUT storage/{path}) existiert,
 * weil die lokale Disk mit "serve => true" konfiguriert ist. Sie nimmt nur
 * signierte Upload-URLs an. Dieser Test haelt fest, dass ein unsignierter
 * Request nichts schreiben kann.
 */
class StorageUploadRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsigned_upload_to_local_storage_is_rejected(): void
    {
        Storage::fake('local');

        $this->call('PUT', '/storage/einbruch.txt', [], [], [], [], 'inhalt')
            ->assertForbidden();

        Storage::disk('local')->assertMissing('einbruch.txt');
    }

    public function test_unsigned_upload_is_rejected_for_logged_in_host_too(): void
    {
        Storage::fake('local');
        $this->actingAsHost();

        $this->call('PUT', '/storage/einbruch.php', [], [], [], [], '<?php echo 1;')
            ->assertForbidden();

        Storage::disk('local')->assertMissing('einbruch.php');
    }
}
