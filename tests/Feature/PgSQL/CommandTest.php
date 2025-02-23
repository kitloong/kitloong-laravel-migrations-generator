<?php

namespace KitLoong\MigrationsGenerator\Tests\Feature\PgSQL;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

class CommandTest extends PgSQLTestCase
{
    public function testRun(): void
    {
        $migrateTemplates = function (): void {
            $this->migrateGeneral();
        };

        $generateMigrations = function (): void {
            $this->generateMigrations();
        };

        $this->verify($migrateTemplates, $generateMigrations);
    }

    public function testSquashUp(): void
    {
        $migrateTemplates = function (): void {
            $this->migrateGeneral();
        };

        $generateMigrations = function (): void {
            $this->generateMigrations(['--squash' => true]);
        };

        $this->verify($migrateTemplates, $generateMigrations);
    }

    public function testCollation(): void
    {
        $migrateTemplates = function (): void {
            $this->migrateCollation();
        };

        $generateMigrations = function (): void {
            $this->generateMigrations(['--use-db-collation' => true]);
        };

        $this->verify($migrateTemplates, $generateMigrations);
    }

    public function testIgnore(): void
    {
        $this->migrateGeneral();

        $this->truncateMigrationsTable();

        $this->generateMigrations([
            '--ignore' => implode(',', [
                'name-with-hyphen',
                'name-with-hyphen_view',
            ]),
        ]);

        $this->refreshDatabase();

        $this->runMigrationsFrom($this->getStorageMigrationsPath());

        $tables = $this->getTableNames();
        $views  = $this->getViewNames();

        $this->assertNotContains('name-with-hyphen', $tables);
        $this->assertNotContains('public."name-with-hyphen_view"', $views);
    }

    /**
     * Start from Laravel 9, the `schema` configuration option used to configure Postgres connection search paths renamed to `search_path`.
     *
     * @see https://laravel.com/docs/9.x/upgrade#postgres-schema-configuration
     */
    public function testWithSearchPath(): void
    {
        // Unset `schema`
        Config::set('database.connections.pgsql.schema');
        $this->assertNull(Config::get('database.connections.pgsql.schema'));

        // Use `search_path`
        Config::set('database.connections.pgsql.search_path', 'public');

        $migrateTemplates = function (): void {
            $this->migrateGeneral();
        };

        $generateMigrations = function (): void {
            $this->generateMigrations();
        };

        $this->verify($migrateTemplates, $generateMigrations);
    }

    public function testWithHasTable(): void
    {
        $migrateTemplates = function (): void {
            $this->migrateGeneral();
        };

        $generateMigrations = function (): void {
            $this->generateMigrations(['--with-has-table' => true]);
        };

        $this->verify($migrateTemplates, $generateMigrations);
    }

    public function testWithHasTableSquash(): void
    {
        $migrateTemplates = function (): void {
            $this->migrateGeneral();
        };

        $generateMigrations = function (): void {
            $this->generateMigrations(['--with-has-table' => true, '--squash' => true]);
        };

        $this->verify($migrateTemplates, $generateMigrations);
    }

    public function testSkipVendor(): void
    {
        $this->migrateGeneral();

        $this->migrateVendors();

        // Load migrations from vendors path to mock vendors migration.
        // Loaded migrations should not be generated.
        app('migrator')->path($this->getStorageFromVendorsPath());

        $tables = $this->getTableNames();

        $vendors = [
            'personal_access_tokens',
            'telescope_entries',
            'telescope_entries_tags',
            'telescope_monitoring',
        ];

        foreach ($vendors as $vendor) {
            $this->assertContains($vendor, $tables);
        }

        $tablesWithoutVendors = (new Collection($tables))->filter(static fn ($table) => !in_array($table, $vendors))
            ->values()
            ->all();

        $this->truncateMigrationsTable();

        $this->generateMigrations(['--skip-vendor' => true]);

        $this->refreshDatabase();

        $this->runMigrationsFrom($this->getStorageMigrationsPath());

        $generatedTables = $this->getTableNames();

        sort($tablesWithoutVendors);
        sort($generatedTables);

        $this->assertSame($tablesWithoutVendors, $generatedTables);
    }

    private function verify(callable $migrateTemplates, callable $generateMigrations): void
    {
        $migrateTemplates();

        $this->truncateMigrationsTable();
        $this->dumpSchemaAs($this->getStorageSqlPath('expected.sql'));

        $generateMigrations();

        $this->assertMigrations();

        $this->refreshDatabase();

        $this->runMigrationsFrom($this->getStorageMigrationsPath());

        $this->truncateMigrationsTable();
        $this->dumpSchemaAs($this->getStorageSqlPath('actual.sql'));

        $this->assertFileEqualsIgnoringOrder(
            $this->getStorageSqlPath('expected.sql'),
            $this->getStorageSqlPath('actual.sql'),
        );
    }
}
