<?php

namespace KitLoong\MigrationsGenerator\Tests\Feature\MariaDB;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use KitLoong\MigrationsGenerator\Support\CheckLaravelVersion;
use KitLoong\MigrationsGenerator\Tests\Feature\FeatureTestCase;
use PDO;

abstract class MariaDBTestCase extends FeatureTestCase
{
    use CheckLaravelVersion;

    /**
     * @inheritDoc
     */
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.default', 'mariadb');
        $app['config']->set('database.connections.mariadb', [
            'driver'         => $this->atLeastLaravel11() ? 'mariadb' : 'mysql',
            'url'            => null,
            'host'           => env('MARIADB_HOST'),
            'port'           => env('MARIADB_PORT'),
            'database'       => env('MARIADB_DATABASE'),
            'username'       => env('MARIADB_USERNAME'),
            'password'       => env('MARIADB_PASSWORD'),
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
        $password = (config('database.connections.mariadb.password') !== '' ?
            '-p\'' . config('database.connections.mariadb.password') . '\'' :
            '');

        $skipColumnStatistics = '';

        if (env('MYSQLDUMP_HAS_OPTION_SKIP_COLUMN_STATISTICS')) {
            $skipColumnStatistics = '--skip-column-statistics';
        }

        $command = sprintf(
            'mysqldump -h %s -P %s -u %s ' . $password . ' %s --compact --no-data ' . $skipColumnStatistics . ' > %s',
            config('database.connections.mariadb.host'),
            config('database.connections.mariadb.port'),
            config('database.connections.mariadb.username'),
            config('database.connections.mariadb.database'),
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
        $procedures = DB::select("SHOW PROCEDURE STATUS where DB='" . config('database.connections.mariadb.database') . "'");

        foreach ($procedures as $procedure) {
            DB::unprepared("DROP PROCEDURE IF EXISTS " . $procedure->Name);
        }
    }
}
