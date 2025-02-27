<?php

namespace KitLoong\MigrationsGenerator\Tests\Feature\MySQL57;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use KitLoong\MigrationsGenerator\Setting;
use PDO;

class StackedCommandTest extends MySQL57TestCase
{
    public function testRunAsCall(): void
    {
        Schema::create('migration_table', static function (Blueprint $table): void {
            $table->increments('id');
        });

        Schema::connection('migration2')->create('migration2_table', static function (Blueprint $table): void {
            $table->increments('id');
        });

        $this->assertTrue(Schema::hasTable('migration_table'));
        $this->assertTrue(Schema::connection('migration2')->hasTable('migration2_table'));

        $this->generateMigrations([
            '--table-filename' => 'create_migration_tables.php',
            '--squash'         => true,
        ]);

        // Setting should reset.
        $this->assertEquals(app(Setting::class), new Setting());

        $this->generateMigrations([
            '--connection'     => 'migration2',
            '--table-filename' => 'create_migration2_tables.php',
            '--squash'         => true,
            ]);

        $files = File::files($this->getStorageMigrationsPath());
        $this->assertCount(2, $files);

        foreach ($files as $file) {
            if (Str::contains($file->getBasename(), 'create_migration_table')) {
                $this->assertStringContainsString(
                    'migration_table',
                    $file->getContents(),
                );
                continue;
            }

            $this->assertStringContainsString(
                'migration2_table',
                $file->getContents(),
            );
        }
    }

    /**
     * @inheritDoc
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.connections.migration2', [
            'driver'         => 'mysql',
            'url'            => null,
            'host'           => env('MYSQL57_HOST'),
            'port'           => env('MYSQL57_PORT'),
            'database'       => 'migration2',
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

    protected function setUp(): void
    {
        parent::setUp();

        Schema::createDatabase('migration2');
    }

    protected function tearDown(): void
    {
        Schema::dropDatabaseIfExists('migration2');

        parent::tearDown();
    }
}
