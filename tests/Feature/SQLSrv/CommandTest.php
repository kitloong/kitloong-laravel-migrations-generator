<?php

namespace KitLoong\MigrationsGenerator\Tests\Feature\SQLSrv;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CommandTest extends SQLSrvTestCase
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

    public function testUnsupportedColumns(): void
    {
        DB::statement(
            "CREATE TABLE custom (
                money money,
                smallmoney smallmoney,
                [name.dot] varchar(255)
            )",
        );

        $this->generateMigrations();

        // Should generate one migration file only.
        $migration = File::files($this->getStorageMigrationsPath())[0];

        $this->assertStringContainsString(
            '$table->decimal(\'money\', 19, 4)->nullable();',
            $migration->getContents(),
        );

        $this->assertStringContainsString(
            '$table->decimal(\'smallmoney\', 10, 4)->nullable();',
            $migration->getContents(),
        );

        $this->assertStringContainsString(
            '$table->string(\'name.dot\')->nullable()',
            $migration->getContents(),
        );
    }

    public function testDown(): void
    {
        $this->migrateGeneral();

        $this->truncateMigrationsTable();

        $this->generateMigrations();

        $this->rollbackMigrationsFrom('sqlsrv', $this->getStorageMigrationsPath());

        $tables = $this->getTableNames();
        $views  = $this->getViewNames();

        $this->assertCount(1, $tables);
        $this->assertCount(0, $views);
        $this->assertSame(0, DB::table('migrations')->count());
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

    public function testGenerateXml(): void
    {
        $this->migrateGeneral();

        // Test xml column
        DB::statement("alter table all_columns add xml xml");

        $this->truncateMigrationsTable();

        $this->generateMigrations();
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
