<?php

namespace Tests\Feature\Community;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * E18 / ADR-005: die fünf Community-Migrationen unter SQLite mit
 * Bestandsdaten (Produktion nutzt SQLite).
 *
 * Wie GoogleLoginMigrationTest bewusst OHNE RefreshDatabase: eigene
 * SQLite-Datei mit eingeschalteten Fremdschlüsseln, so wie `php artisan
 * migrate` in Produktion läuft. Die neuen Fremdschlüssel auf `recipes` und
 * `ingredients` lassen Laravel diese Tabellen neu aufbauen – dabei dürfen
 * keine Kaskaden (recipe_images, recipe_ingredients, ingredient_alternatives,
 * collection_recipes, …) auslösen.
 */
class CommunityMigrationTest extends TestCase
{
    private const CONNECTION = 'community_migration_probe';

    private const MIGRATIONS = [
        '2026_11_21_140000_add_community_columns_to_recipes_table',
        '2026_11_21_140100_add_proposal_columns_to_ingredients_table',
        '2026_11_21_140200_create_recipe_user_ratings_table',
        '2026_11_21_140300_create_recipe_reports_table',
        '2026_11_21_140400_add_gemini_api_key_to_users_table',
    ];

    private string $databaseFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseFile = storage_path('framework/testing/community-migration-'.getmypid().'.sqlite');
        File::ensureDirectoryExists(dirname($this->databaseFile));
        File::delete($this->databaseFile);
        touch($this->databaseFile);

        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'sqlite',
            'database' => $this->databaseFile,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        // Stand vor E18 (wie die Produktion heute)
        $paths = collect(File::files(database_path('migrations')))
            ->map(fn ($file) => $file->getRealPath())
            ->reject(fn (string $path) => collect(self::MIGRATIONS)->contains(fn ($m) => str_contains($path, $m)))
            ->values()
            ->all();

        Artisan::call('migrate', ['--database' => self::CONNECTION, '--path' => $paths, '--realpath' => true, '--force' => true]);
        $this->assertFalse($this->db()->getSchemaBuilder()->hasColumn('recipes', 'type'));
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);
        File::delete($this->databaseFile);

        parent::tearDown();
    }

    public function test_migration_keeps_all_data_indexes_and_foreign_keys(): void
    {
        $this->seedLegacyData();
        $this->assertSame(1, (int) $this->db()->scalar('PRAGMA foreign_keys'));
        $before = $this->snapshot();

        $this->migrate();
        $after = $this->snapshot();

        // Neue Spalten mit Defaults, alle alten unverändert
        $added = [
            'recipes' => ['type', 'status', 'user_id', 'rejection_reason', 'submitted_at', 'reviewed_at', 'reviewed_by'],
            'ingredients' => ['status', 'proposed_by'],
            'users' => ['gemini_api_key'],
        ];
        foreach ($added as $table => $columns) {
            $this->assertSame($columns, array_values(array_diff(array_keys($after['columns'][$table]), array_keys($before['columns'][$table]))), $table);
            foreach ($before['columns'][$table] as $name => $column) {
                $this->assertSame($column, $after['columns'][$table][$name], "Spalte {$table}.{$name}");
            }
        }
        $this->assertSame("'host'", $after['columns']['recipes']['type']['default']);
        $this->assertSame("'approved'", $after['columns']['recipes']['status']['default']);
        $this->assertSame("'approved'", $after['columns']['ingredients']['status']['default']);

        // Bestandsdaten: identisch, nur um die Defaults ergänzt (keine UPDATEs)
        $defaults = [
            'recipes' => ['type' => 'host', 'status' => 'approved', 'user_id' => null, 'rejection_reason' => null, 'submitted_at' => null, 'reviewed_at' => null, 'reviewed_by' => null],
            'ingredients' => ['status' => 'approved', 'proposed_by' => null],
            'users' => ['gemini_api_key' => null],
        ];
        foreach ($before['data'] as $table => $rows) {
            $expected = isset($defaults[$table])
                ? array_map(fn (array $row) => [...$row, ...$defaults[$table]], $rows)
                : $rows;
            $this->assertEquals($expected, $after['data'][$table], "Daten in {$table}");
        }
        $this->assertCount(2, $after['data']['recipes']);
        $this->assertCount(2, $after['data']['recipe_images']);
        $this->assertCount(3, $after['data']['recipe_ingredients']);
        $this->assertCount(1, $after['data']['ingredient_alternatives']);

        // Andere Tabellen: Schema unverändert (inkl. Fremdschlüssel auf recipes/ingredients)
        foreach (['columns', 'indexes', 'foreign_keys'] as $part) {
            foreach ($before[$part] as $table => $definition) {
                if (! isset($added[$table])) {
                    $this->assertSame($definition, $after[$part][$table], "{$part} von {$table}");
                }
            }
        }
        // alte Indizes der umgebauten Tabellen bleiben, neue kommen hinzu
        foreach (['recipes', 'ingredients'] as $table) {
            foreach ($before['indexes'][$table] as $name => $index) {
                $this->assertSame($index, $after['indexes'][$table][$name] ?? null, "Index {$name}");
            }
        }
        $this->assertArrayHasKey('recipes_type_status_index', $after['indexes']['recipes']);
        $this->assertArrayHasKey('ingredients_name_unique', $after['indexes']['ingredients']);

        // neue Fremdschlüssel
        $recipeFks = collect($after['foreign_keys']['recipes'])->keyBy(fn ($f) => $f['columns'][0]);
        $this->assertSame(['users', 'cascade'], [$recipeFks['user_id']['foreign_table'], $recipeFks['user_id']['on_delete']]);
        $this->assertSame(['users', 'set null'], [$recipeFks['reviewed_by']['foreign_table'], $recipeFks['reviewed_by']['on_delete']]);

        $this->assertSame([], $this->db()->select('PRAGMA foreign_key_check'));
        $this->assertSame('ok', $this->db()->scalar('PRAGMA integrity_check'));
        $this->assertSame(1, (int) $this->db()->scalar('PRAGMA foreign_keys'));

        // Kaskaden wirken weiter: Rezept löschen entfernt Bilder und Zutatenzeilen
        $this->db()->table('recipes')->where('id', 1)->delete();
        $this->assertSame(1, $this->db()->table('recipe_images')->count());
        $this->assertSame(0, $this->db()->table('recipe_ingredients')->where('recipe_id', 1)->count());
        $this->assertSame(0, $this->db()->table('collection_recipes')->where('recipe_id', 1)->count());
    }

    public function test_rollback_restores_previous_schema_and_data(): void
    {
        $this->seedLegacyData();
        $before = $this->snapshot();

        $this->migrate();
        Artisan::call('migrate:rollback', ['--database' => self::CONNECTION, '--step' => count(self::MIGRATIONS), '--force' => true]);

        $after = $this->snapshot();
        $this->assertSame($before['columns'], $after['columns']);
        $this->assertEquals($before['indexes'], $after['indexes']);
        $this->assertEquals($before['foreign_keys'], $after['foreign_keys']);
        $this->assertEquals($before['data'], $after['data']);
        $this->assertSame([], $this->db()->select('PRAGMA foreign_key_check'));
    }

    public function test_rollback_refuses_while_community_recipes_exist(): void
    {
        $this->seedLegacyData();
        $this->migrate();
        $this->db()->table('recipes')->insert(['title' => 'C', 'instructions' => '1. x', 'type' => 'community', 'status' => 'pending', 'user_id' => 2]);
        // die übrigen E18-Migrationen sind leer bzw. ohne Daten -> nur die erste blockiert
        $schema = $this->snapshot();

        try {
            Artisan::call('migrate:rollback', ['--database' => self::CONNECTION, '--step' => count(self::MIGRATIONS), '--force' => true]);
            $this->fail('Rollback hätte abbrechen müssen.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Rollback abgebrochen: 1 Community-Rezept', $e->getMessage());
        }

        $this->assertSame($schema['data']['recipes'], $this->snapshot()['data']['recipes']);
        $this->assertTrue($this->db()->getSchemaBuilder()->hasColumn('recipes', 'type'));
    }

    // ------------------------------------------------------------------

    private function db(): Connection
    {
        return DB::connection(self::CONNECTION);
    }

    private function migrate(): void
    {
        $this->assertSame(0, Artisan::call('migrate', ['--database' => self::CONNECTION, '--force' => true]));
        foreach (self::MIGRATIONS as $migration) {
            $this->assertTrue($this->db()->table('migrations')->where('migration', $migration)->exists(), $migration);
        }
    }

    /** Bestandsdaten wie in Produktion: Rezepte mit Bildern, Zutaten, Alternativen, Bewertungen, Sammlungen. */
    private function seedLegacyData(): void
    {
        $db = $this->db();
        $now = '2026-01-02 03:04:05';

        $db->table('users')->insert([
            ['id' => 1, 'name' => 'Host', 'email' => 'host@example.test', 'password' => 'x', 'is_host' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Erika', 'email' => 'erika@example.test', 'password' => 'y', 'is_host' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $db->table('recipes')->insert([
            ['id' => 1, 'title' => 'Linsen', 'description' => 'D', 'instructions' => "1. A\nNotizen:\nN", 'duration_minutes' => 30, 'servings' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'title' => 'Oats', 'description' => null, 'instructions' => '1. B', 'duration_minutes' => null, 'servings' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $db->table('recipe_images')->insert([
            ['recipe_id' => 1, 'image_path' => 'recipes/1.png', 'created_at' => $now, 'updated_at' => $now],
            ['recipe_id' => 2, 'image_path' => 'recipes/2.png', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $db->table('ingredients')->insert([
            ['id' => 1, 'name' => 'Linsen', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Ümlaut-Öl', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Hafer', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $db->table('recipe_ingredients')->insert([
            ['recipe_id' => 1, 'ingredient_id' => 1, 'note' => '200 g', 'created_at' => $now, 'updated_at' => $now],
            ['recipe_id' => 1, 'ingredient_id' => 2, 'note' => '1 EL', 'created_at' => $now, 'updated_at' => $now],
            ['recipe_id' => 2, 'ingredient_id' => 3, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $db->table('ingredient_alternatives')->insert(['recipe_id' => 1, 'ingredient_id' => 2, 'alternative_ingredient_id' => 3, 'created_at' => $now, 'updated_at' => $now]);
        $db->table('categories')->insert(['id' => 1, 'name' => 'Vegan', 'created_at' => $now, 'updated_at' => $now]);
        $db->table('recipe_categories')->insert(['recipe_id' => 1, 'category_id' => 1]);
        $db->table('rating_dimensions')->insert(['id' => 1, 'name' => 'Geschmack', 'created_at' => $now, 'updated_at' => $now]);
        $db->table('recipe_ratings')->insert(['recipe_id' => 1, 'dimension_id' => 1, 'value' => 5, 'created_at' => $now, 'updated_at' => $now]);
        $db->table('collections')->insert(['id' => 1, 'user_id' => 2, 'name' => 'Wochenplan', 'created_at' => $now, 'updated_at' => $now]);
        $db->table('collection_recipes')->insert([['collection_id' => 1, 'recipe_id' => 1], ['collection_id' => 1, 'recipe_id' => 2]]);
        $db->table('comments')->insert(['user_id' => 2, 'recipe_id' => 1, 'content' => 'Lecker', 'created_at' => $now]);
        $db->table('recipe_imports')->insert(['user_id' => 1, 'url' => 'https://www.tiktok.com/@x/video/1', 'status' => 'done', 'created_at' => $now, 'updated_at' => $now]);
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
                ->sortBy(fn ($f) => implode(',', $f['columns']))
                ->values()
                ->all();

            $snapshot['data'][$table] = $this->db()->table($table)->orderByRaw('rowid')->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        }

        ksort($snapshot['columns']);
        ksort($snapshot['data']);

        return $snapshot;
    }
}
