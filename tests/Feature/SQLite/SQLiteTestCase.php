<?php

namespace KitLoong\MigrationsGenerator\Tests\Feature\SQLite;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use KitLoong\MigrationsGenerator\Tests\Feature\FeatureTestCase;

abstract class SQLiteTestCase extends FeatureTestCase
{
    /**
     * @inheritDoc
     */
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        touch((string) env('SQLITE_DATABASE'));

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'                  => 'sqlite',
            'url'                     => env('SQLITE_URL'),
            'database'                => env('SQLITE_DATABASE'),
            'prefix'                  => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function dumpSchemaAs(string $destination): void
    {
        $command = sprintf(
            'sqlite3 %s .dump > %s',
            config('database.connections.sqlite.database'),
            $destination,
        );
        exec($command);
    }

    protected function refreshDatabase(): void
    {
        $prefix = DB::getTablePrefix();
        DB::setTablePrefix('');
        Schema::dropAllViews();
        Schema::dropAllTables();
        DB::setTablePrefix($prefix);
    }
}
