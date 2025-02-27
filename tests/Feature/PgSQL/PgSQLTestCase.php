<?php

namespace KitLoong\MigrationsGenerator\Tests\Feature\PgSQL;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use KitLoong\MigrationsGenerator\Tests\Feature\FeatureTestCase;

abstract class PgSQLTestCase extends FeatureTestCase
{
    /**
     * @inheritDoc
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver'         => 'pgsql',
            'url'            => env('DATABASE_URL'),
            'host'           => env('POSTGRES_HOST'),
            'port'           => env('POSTGRES_PORT'),
            'database'       => env('POSTGRES_DATABASE'),
            'username'       => env('POSTGRES_USERNAME'),
            'password'       => env('POSTGRES_PASSWORD'),
            'charset'        => 'utf8',
            'prefix'         => '',
            'prefix_indexes' => true,
            'schema'         => 'public',
            'sslmode'        => 'prefer',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create for user defined type column test.
        DB::statement("CREATE TYPE my_status AS enum ('PENDING', 'ACTIVE', 'SUSPENDED')");
    }

    protected function tearDown(): void
    {
        DB::statement("DROP TYPE IF EXISTS my_status CASCADE");

        parent::tearDown();
    }

    protected function dumpSchemaAs(string $destination): void
    {
        $command = sprintf(
            'PGPASSWORD="%s" pg_dump -h %s -p %s -U %s %s -f %s --schema-only',
            config('database.connections.pgsql.password'),
            config('database.connections.pgsql.host'),
            config('database.connections.pgsql.port'),
            config('database.connections.pgsql.username'),
            config('database.connections.pgsql.database'),
            $destination,
        );
        exec($command);
    }

    protected function refreshDatabase(): void
    {
        $this->dropAllTablesAndViews();
        $this->dropAllProcedures();
    }

    protected function dropAllTablesAndViews(): void
    {
        (new Collection(Schema::getTables()))
            ->each(static function (array $table): void {
                if ($table['name'] === 'spatial_ref_sys') {
                    return;
                }

                // Schema name defined in the framework configuration.
                $searchPath = DB::connection()->getConfig('search_path') ?: DB::connection()->getConfig('schema');

                if ($table['schema'] !== $searchPath) {
                    return;
                }

                // CASCADE, automatically drop objects that depend on the table.
                // This statement will drop views which depend on the table.
                DB::statement("DROP TABLE IF EXISTS \"" . $table['name'] . "\" cascade");
            });
    }

    protected function dropAllProcedures(): void
    {
        $searchPath = DB::connection()->getConfig('search_path') ?: DB::connection()->getConfig('schema');

        $procedures = DB::select(
            "SELECT *, pg_get_functiondef(pg_proc.oid)
            FROM pg_catalog.pg_proc
                JOIN pg_namespace ON pg_catalog.pg_proc.pronamespace = pg_namespace.oid
            WHERE prokind = 'p'
                AND pg_namespace.nspname = '" . $searchPath . "'",
        );

        foreach ($procedures as $procedure) {
            DB::unprepared("DROP PROCEDURE IF EXISTS " . $procedure->proname);
        }
    }
}
