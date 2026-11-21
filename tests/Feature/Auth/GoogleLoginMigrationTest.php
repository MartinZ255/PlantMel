<?php

namespace Tests\Feature\Auth;

use Illuminate\Database\Connection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * E17 / ADR-004: Migration 2026_11_21_120000_add_google_id_to_users_table
 * unter SQLite (Produktion nutzt SQLite).
 *
 * Bewusst OHNE RefreshDatabase: Dessen Transaktion würde das
 * `PRAGMA foreign_keys = OFF` wirkungslos machen, mit dem Laravel den
 * Tabellen-Neuaufbau bei `->change()` absichert. Stattdessen eine eigene
 * SQLite-Datei mit eingeschalteten Fremdschlüsseln, so wie `php artisan
 * migrate` in Produktion läuft (SQLite-Migrationen ohne Transaktion).
 */
class GoogleLoginMigrationTest extends TestCase
{
    private const CONNECTION = 'google_migration_probe';

    private const MIGRATION = '2026_11_21_120000_add_google_id_to_users_table';

    private string $databaseFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseFile = storage_path('framework/testing/google-migration-'.getmypid().'.sqlite');
        File::ensureDirectoryExists(dirname($this->databaseFile));
        File::delete($this->databaseFile);
        touch($this->databaseFile);

        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'sqlite',
            'database' => $this->databaseFile,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        // Stand vor der neuen Migration (wie die Produktion heute)
        $paths = collect(File::files(database_path('migrations')))
            ->map(fn ($file) => $file->getRealPath())
            ->reject(fn (string $path) => str_contains($path, self::MIGRATION))
            ->values()
            ->all();

        Artisan::call('migrate', ['--database' => self::CONNECTION, '--path' => $paths, '--realpath' => true, '--force' => true]);
        $this->assertFalse($this->db()->getSchemaBuilder()->hasColumn('users', 'google_id'));
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);
        File::delete($this->databaseFile);

        parent::tearDown();
    }

    public function test_migration_keeps_all_data_columns_indexes_and_foreign_keys(): void
    {
        $this->seedLegacyData();
        $this->assertSame(1, (int) $this->db()->scalar('PRAGMA foreign_keys'));

        $before = $this->snapshot();

        $this->migrate();

        $after = $this->snapshot();

        // Spalten: alle alten unverändert, password jetzt nullable, google_id neu
        $oldColumns = $before['columns']['users'];
        $newColumns = $after['columns']['users'];
        $this->assertSame(['google_id'], array_values(array_diff(array_keys($newColumns), array_keys($oldColumns))));
        foreach ($oldColumns as $name => $column) {
            $expected = $name === 'password' ? [...$column, 'nullable' => true] : $column;
            $this->assertSame($expected, $newColumns[$name], "Spalte users.{$name}");
        }
        $this->assertTrue($newColumns['google_id']['nullable']);
        $this->assertStringContainsString('varchar', $newColumns['google_id']['type']);

        // Indizes: alte bleiben, google_id unique kommt hinzu
        $this->assertSame(
            [...$before['indexes']['users'], 'users_google_id_unique' => ['columns' => ['google_id'], 'unique' => true]],
            $after['indexes']['users'],
        );

        // Andere Tabellen: Schema unverändert (inkl. Fremdschlüssel auf users)
        foreach (['columns', 'indexes', 'foreign_keys'] as $part) {
            foreach ($before[$part] as $table => $definition) {
                if ($table !== 'users' || $part === 'foreign_keys') {
                    $this->assertSame($definition, $after[$part][$table], "{$part} von {$table}");
                }
            }
        }
        $this->assertSame('users', $after['foreign_keys']['collections'][0]['foreign_table']);
        $this->assertSame('cascade', $after['foreign_keys']['collections'][0]['on_delete']);

        // Daten: alle Tabellen identisch, users nur um google_id = null ergänzt
        foreach ($before['data'] as $table => $rows) {
            $expected = $table === 'users'
                ? array_map(fn (array $row) => [...$row, 'google_id' => null], $rows)
                : $rows;
            $this->assertEquals($expected, $after['data'][$table], "Daten in {$table}");
        }
        $this->assertCount(3, $after['data']['users']);
        $this->assertCount(2, $after['data']['collections']);

        // Integrität und Kaskade funktionieren weiter
        $this->assertSame([], $this->db()->select('PRAGMA foreign_key_check'));
        $this->assertSame('ok', $this->db()->scalar('PRAGMA integrity_check'));
        $this->assertSame(1, (int) $this->db()->scalar('PRAGMA foreign_keys'));

        $this->db()->table('users')->where('email', 'erika@example.test')->delete();
        $this->assertSame(1, $this->db()->table('collections')->count(), 'ON DELETE CASCADE wirkt weiter.');
    }

    public function test_after_migration_password_may_be_null_and_google_id_is_unique(): void
    {
        $this->migrate();

        $this->db()->table('users')->insert(['name' => 'G', 'email' => 'g@example.test', 'password' => null, 'google_id' => 'g-1']);
        $this->db()->table('users')->insert(['name' => 'H', 'email' => 'h@example.test', 'password' => null, 'google_id' => null]);
        $this->db()->table('users')->insert(['name' => 'I', 'email' => 'i@example.test', 'password' => null, 'google_id' => null]);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->db()->table('users')->insert(['name' => 'J', 'email' => 'j@example.test', 'password' => 'x', 'google_id' => 'g-1']);
    }

    public function test_rollback_restores_previous_schema_and_data(): void
    {
        $this->seedLegacyData();
        $before = $this->snapshot();

        $this->migrate();
        $this->db()->table('users')->where('email', 'host@example.test')->update(['google_id' => 'g-host']);

        Artisan::call('migrate:rollback', ['--database' => self::CONNECTION, '--step' => 1, '--force' => true]);

        $after = $this->snapshot();
        $this->assertSame($before['columns'], $after['columns']);
        $this->assertSame($before['indexes'], $after['indexes']);
        $this->assertSame($before['foreign_keys'], $after['foreign_keys']);
        $this->assertEquals($before['data'], $after['data']);
        $this->assertSame([], $this->db()->select('PRAGMA foreign_key_check'));
    }

    public function test_rollback_refuses_while_accounts_without_password_exist(): void
    {
        $this->seedLegacyData();
        $this->migrate();
        $this->db()->table('users')->insert(['name' => 'G', 'email' => 'g@example.test', 'password' => null, 'google_id' => 'g-1']);
        $schema = $this->snapshot();

        try {
            Artisan::call('migrate:rollback', ['--database' => self::CONNECTION, '--step' => 1, '--force' => true]);
            $this->fail('Rollback hätte abbrechen müssen.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Rollback abgebrochen: 1 Konto/Konten ohne Passwort', $e->getMessage());
        }

        $this->assertEquals($schema, $this->snapshot(), 'Schema und Daten bleiben unverändert.');
        $this->assertTrue($this->db()->table('migrations')->where('migration', self::MIGRATION)->exists());
    }

    // ------------------------------------------------------------------
    // Helfer
    // ------------------------------------------------------------------

    private function db(): Connection
    {
        return DB::connection(self::CONNECTION);
    }

    private function migrate(): void
    {
        $this->assertSame(0, Artisan::call('migrate', ['--database' => self::CONNECTION, '--force' => true]));
        $this->assertTrue($this->db()->table('migrations')->where('migration', self::MIGRATION)->exists());
        $this->assertTrue($this->db()->getSchemaBuilder()->hasColumn('users', 'google_id'));
    }

    /** Bestandsdaten wie in Produktion: Host, Nutzer mit 2FA-Resten, Sammlungen, Kommentare, Importe, Sitzungen. */
    private function seedLegacyData(): void
    {
        $db = $this->db();
        $now = '2026-01-02 03:04:05';

        $db->table('users')->insert([
            ['id' => 1, 'name' => 'Host', 'email' => 'host@example.test', 'email_verified_at' => $now, 'password' => '$2y$04$hosthashhosthashhosthashhosthashhosthashhosthashhost', 'is_host' => 1, 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'remember_token' => 'tok1', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Erika', 'email' => 'erika@example.test', 'email_verified_at' => null, 'password' => '$2y$04$erikahasherikahasherikahasherikahasherikahasherika', 'is_host' => 0, 'two_factor_secret' => 'alt', 'two_factor_recovery_codes' => '[]', 'two_factor_confirmed_at' => $now, 'remember_token' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Ümlaut Ärger', 'email' => 'Gross@Example.TEST', 'email_verified_at' => $now, 'password' => 'x', 'is_host' => 0, 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'remember_token' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $db->table('collections')->insert([
            ['user_id' => 2, 'name' => 'Erikas Sammlung', 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => 1, 'name' => 'Host-Sammlung', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $recipeId = $db->table('recipes')->insertGetId(['title' => 'Rezept', 'instructions' => '1. Kochen', 'created_at' => $now, 'updated_at' => $now]);
        $db->table('comments')->insert(['user_id' => 2, 'recipe_id' => $recipeId, 'content' => 'Lecker', 'created_at' => $now]);
        $db->table('recipe_imports')->insert(['user_id' => 1, 'url' => 'https://www.tiktok.com/@x/video/1', 'status' => 'done', 'created_at' => $now, 'updated_at' => $now]);
        $db->table('sessions')->insert(['id' => 'sess-1', 'user_id' => 2, 'payload' => 'p', 'last_activity' => 1]);
        $db->table('password_reset_tokens')->insert(['email' => 'erika@example.test', 'token' => 't', 'created_at' => $now]);
    }

    /**
     * @return array{columns: array<string, mixed>, indexes: array<string, mixed>, foreign_keys: array<string, mixed>, data: array<string, mixed>}
     */
    private function snapshot(): array
    {
        $schema = $this->db()->getSchemaBuilder();
        $snapshot = ['columns' => [], 'indexes' => [], 'foreign_keys' => [], 'data' => []];

        foreach ($schema->getTableListing(schemaQualified: false) as $table) {
            if ($table === 'migrations') {
                continue;
            }

            $snapshot['columns'][$table] = collect($schema->getColumns($table))
                ->keyBy('name')
                ->map(fn (array $c) => ['type' => $c['type'], 'nullable' => $c['nullable'], 'default' => $c['default'], 'auto_increment' => $c['auto_increment']])
                ->all();

            $snapshot['indexes'][$table] = collect($schema->getIndexes($table))
                ->keyBy('name')
                ->map(fn (array $i) => ['columns' => $i['columns'], 'unique' => $i['unique']])
                ->sortKeys()
                ->all();

            $snapshot['foreign_keys'][$table] = collect($schema->getForeignKeys($table))
                ->map(fn (array $f) => ['columns' => $f['columns'], 'foreign_table' => $f['foreign_table'], 'foreign_columns' => $f['foreign_columns'], 'on_delete' => $f['on_delete']])
                ->values()
                ->all();

            $snapshot['data'][$table] = $this->db()->table($table)->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        }

        ksort($snapshot['columns']);

        return $snapshot;
    }
}
