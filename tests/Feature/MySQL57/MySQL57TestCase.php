<?php

namespace KitLoong\MigrationsGenerator\Tests\Feature\MySQL57;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use KitLoong\MigrationsGenerator\Tests\Feature\FeatureTestCase;
use PDO;

abstract class MySQL57TestCase extends FeatureTestCase
{
    /**
     * @inheritDoc
     */
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.default', 'mysql57');
        $app['config']->set('database.connections.mysql57', [
            'driver'         => 'mysql',
            'url'            => null,
            'host'           => env('MYSQL57_HOST'),
            'port'           => env('MYSQL57_PORT'),
            'database'       => env('MYSQL57_DATABASE'),
            'username'       => env('MYSQL57_USERNAME'),
            'password'       => env('MYSQL57_PASSWORD'),
            'unix_socket'    => env('DB_SOCKET', ''),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_general_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => null,
            'options'        => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ]);
    }

    protected function dumpSchemaAs(string $destination): void
    {
        $password = (config('database.connections.mysql57.password') !== '' ?
            '-p\'' . config('database.connections.mysql57.password') . '\'' :
            '');

        $skipColumnStatistics = '';

        if (env('MYSQLDUMP_HAS_OPTION_SKIP_COLUMN_STATISTICS')) {
            $skipColumnStatistics = '--skip-column-statistics';
        }

        $command = sprintf(
            // Disable column-statistics to dump MySQL 5.7
            'mysqldump -h %s -P %s -u %s ' . $password . ' %s --compact --no-data --routines ' . $skipColumnStatistics . ' > %s',
            config('database.connections.mysql57.host'),
            config('database.connections.mysql57.port'),
            config('database.connections.mysql57.username'),
            config('database.connections.mysql57.database'),
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
        $this->dropAllProcedures();
    }

    protected function dropAllProcedures(): void
    {
        $procedures = DB::select("SHOW PROCEDURE STATUS where DB='" . config('database.connections.mysql57.database') . "'");

        foreach ($procedures as $procedure) {
            DB::unprepared("DROP PROCEDURE IF EXISTS " . $procedure->Name);
        }
    }
}
