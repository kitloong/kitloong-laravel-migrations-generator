<?php

namespace KitLoong\MigrationsGenerator\Tests\Feature;

use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use KitLoong\MigrationsGenerator\Tests\TestCase;

abstract class FeatureTestCase extends TestCase
{
    abstract protected function refreshDatabase(): void;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStoragePath();
    }

    protected function tearDown(): void
    {
        $this->refreshDatabase();

        parent::tearDown();
    }

    protected function prepareStoragePath(): void
    {
        File::deleteDirectory(storage_path());
        File::makeDirectory($this->getStorageMigrationsPath(), 0775, true);
        File::makeDirectory($this->getStorageFromPath());
        File::makeDirectory($this->getStorageSqlPath());
    }

    protected function getStorageMigrationsPath(string $path = ''): string
    {
        return storage_path('migrations') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    protected function getStorageFromPath(string $path = ''): string
    {
        return storage_path('from') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    protected function getStorageFromVendorsPath(string $path = ''): string
    {
        return storage_path("from/vendors") . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    protected function getStorageSqlPath(string $path = ''): string
    {
        return storage_path('sql') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    protected function migrateGeneral(): void
    {
        $this->migrateFromTemplate(base_path('tests/resources/database/migrations/general'));
    }

    protected function migrateForeign(): void
    {
        $this->migrateFromTemplate(base_path('tests/resources/database/migrations/foreign'));
    }

    protected function migrateCollation(): void
    {
        $this->migrateFromTemplate(base_path('tests/resources/database/migrations/collation'));
    }

    protected function migrateVendors(): void
    {
        $this->migrateFromVendorsTemplate(base_path('tests/resources/database/migrations/vendors'));
    }

    protected function migrateFromTemplate(string $templatePath): void
    {
        File::copyDirectory($templatePath, $this->getStorageFromPath());
        $this->runMigrationsFrom($this->getStorageFromPath());
    }

    protected function migrateFromVendorsTemplate(string $templatePath): void
    {
        File::copyDirectory($templatePath, $this->getStorageFromVendorsPath());
        $this->runMigrationsFrom($this->getStorageFromVendorsPath());
    }

    protected function runMigrationsFrom(string $path): void
    {
        $this->artisan('migrate', [
            '--realpath' => true,
            '--path'     => $path,
        ]);
    }

    protected function rollbackMigrationsFrom(string $connection, string $path): void
    {
        $this->artisan('migrate:rollback', [
            '--database' => $connection,
            '--realpath' => true,
            '--path'     => $path,
        ]);
    }

    /**
     * Generate migration files to $this->storageMigrations()
     *
     * @param  array<string, string|bool|int>  $options
     * @see \KitLoong\MigrationsGenerator\Tests\Feature\FeatureTestCase::defineEnvironment()
     */
    protected function generateMigrations(array $options = []): void
    {
        $expectConnectionQuestion = false;

        if (
            array_key_exists('--connection', $options)
            && $options['--connection'] !== config('database.default')
        ) {
            $expectConnectionQuestion = true;
        }

        $command = $this->artisan(
            'migrate:generate',
            array_merge([
                '--path' => $this->getStorageMigrationsPath(),
            ], $options),
        );

        if (is_int($command)) {
            return;
        }

        $command->expectsQuestion('Do you want to log these migrations in the migrations table?', true);

        if ($expectConnectionQuestion) {
            $command->expectsQuestion(
                'Log into current connection: ' . $options['--connection'] . '? [Y = ' . $options['--connection'] . ', n = ' . config('database.default') . ' (default connection)]',
                true,
            );
        }

        $command->expectsQuestion(
            'Next Batch Number is: 1. We recommend using Batch Number 0 so that it becomes the "first" migration. [Default: 0]',
            '0',
        );
    }

    protected function assertMigrations(): void
    {
        $migrations = [];

        foreach (File::files($this->getStorageMigrationsPath()) as $migration) {
            $migrations[] = $migration->getFilenameWithoutExtension();
        }

        $dbMigrations = app(MigrationRepositoryInterface::class)->getRan();

        // Both file and DB migrations are sorted by name ascending however the result is slightly different.
        // Use PHP sort here to maintain same ordering.
        sort($migrations);
        sort($dbMigrations);

        $this->assertSame($migrations, $dbMigrations);
    }

    protected function truncateMigrationsTable(): void
    {
        DB::table('migrations')->truncate();
    }

    /**
     * Get a list of table names.
     *
     * @return string[]
     */
    protected function getTableNames(): array
    {
        return array_column(Schema::getTables(), 'name');
    }

    /**
     * Get a list of view names.
     *
     * @return string[]
     */
    protected function getViewNames(): array
    {
        return array_column(Schema::getViews(), 'name');
    }
}
