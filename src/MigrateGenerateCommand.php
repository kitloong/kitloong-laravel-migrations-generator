<?php

namespace KitLoong\MigrationsGenerator;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use KitLoong\MigrationsGenerator\Enum\Driver;
use KitLoong\MigrationsGenerator\Migration\ForeignKeyMigration;
use KitLoong\MigrationsGenerator\Migration\Migrator\Migrator;
use KitLoong\MigrationsGenerator\Migration\ProcedureMigration;
use KitLoong\MigrationsGenerator\Migration\Squash;
use KitLoong\MigrationsGenerator\Migration\TableMigration;
use KitLoong\MigrationsGenerator\Migration\ViewMigration;
use KitLoong\MigrationsGenerator\Schema\Models\Procedure;
use KitLoong\MigrationsGenerator\Schema\Models\View;
use KitLoong\MigrationsGenerator\Schema\MySQLSchema;
use KitLoong\MigrationsGenerator\Schema\PgSQLSchema;
use KitLoong\MigrationsGenerator\Schema\Schema;
use KitLoong\MigrationsGenerator\Schema\SQLiteSchema;
use KitLoong\MigrationsGenerator\Schema\SQLSrvSchema;

class MigrateGenerateCommand extends Command
{
    /**
     * @inheritDoc
     */
    protected $signature = 'migrate:generate
                            {tables? : A list of tables or views you wish to generate migrations for separated by a comma: users,posts,comments}
                            {--c|connection= : The database connection to use}
                            {--t|tables= : A list of tables or views you wish to generate migrations for separated by a comma: users,posts,comments}
                            {--i|ignore= : A list of tables or views you wish to ignore, separated by a comma: users,posts,comments}
                            {--p|path= : Where should the file be created?}
                            {--tp|template-path= : The location of the template for this generator}
                            {--date= : Migrations will be created with specified date. Views and foreign keys will be created with + 1 second. Date should be in format supported by Carbon::parse}
                            {--table-filename= : Define table migration filename, default pattern: [datetime]_create_[name]_table.php}
                            {--view-filename= : Define view migration filename, default pattern: [datetime]_create_[name]_view.php}
                            {--proc-filename= : Define stored procedure migration filename, default pattern: [datetime]_create_[name]_proc.php}
                            {--fk-filename= : Define foreign key migration filename, default pattern: [datetime]_add_foreign_keys_to_[name]_table.php}
                            {--log-with-batch= : Log migrations with given batch number. We recommend using batch number 0 so that it becomes the first migration}
                            {--default-index-names : Don\'t use DB index names for migrations}
                            {--default-fk-names : Don\'t use DB foreign key names for migrations}
                            {--use-db-collation : Generate migrations with existing DB collation}
                            {--skip-log : Don\'t log into migrations table}
                            {--skip-vendor : Don\'t generate vendor migrations}
                            {--skip-views : Don\'t generate views}
                            {--skip-proc : Don\'t generate stored procedures}
                            {--squash : Generate all migrations into a single file}
                            {--with-has-table : Check for the existence of a table using `hasTable`}';

    /**
     * @inheritDoc
     */
    protected $description = 'Generate migrations from an existing table structure.';

    protected Schema $schema;

    protected bool $shouldLog = false;

    protected int $nextBatchNumber = 0;
    protected MigrationRepositoryInterface $repository;
    protected Squash $squash;
    protected ForeignKeyMigration $foreignKeyMigration;
    protected ProcedureMigration $procedureMigration;
    protected ViewMigration $viewMigration;
    protected TableMigration $tableMigration;

    /**
     * Execute the console command.
     *
     * @throws \Exception
     */
    public function handle(
        MigrationRepositoryInterface $repository,
        Squash $squash,
        ForeignKeyMigration $foreignKeyMigration,
        ProcedureMigration $procedureMigration,
        TableMigration $tableMigration,
        ViewMigration $viewMigration,
    ): void {
        $this->tableMigration      = $tableMigration;
        $this->viewMigration       = $viewMigration;
        $this->procedureMigration  = $procedureMigration;
        $this->foreignKeyMigration = $foreignKeyMigration;
        $this->squash              = $squash;
        $this->repository          = $repository;

        $previousConnection = DB::getDefaultConnection();

        try {
            $this->setup($previousConnection);

            $connection = $this->option('connection') ?: $previousConnection;

            DB::setDefaultConnection($connection);

            $this->schema = $this->makeSchema();

            $this->info('Using connection: ' . $connection . "\n");

            $tables       = $this->filterTables()->sort()->values();
            $views        = $this->filterViews()->sort()->values();
            $generateList = $tables->merge($views)->unique();

            $this->info('Generating migrations for: ' . $generateList->implode(',') . "\n");

            $this->askIfLogMigrationTable($previousConnection);

            $this->generate($tables, $views);

            $this->info("\nFinished!\n");

            if (DB::getDriverName() === Driver::SQLITE->value) {
                $this->warn('SQLite only supports foreign keys upon creation of the table and not when tables are altered.');
                $this->warn('See https://www.sqlite.org/omitted.html');
                $this->warn('*_add_foreign_keys_* migrations were generated, however will get omitted if migrate to SQLite type database.');
            }
        } finally {
            DB::setDefaultConnection($previousConnection);
            app()->forgetInstance(Setting::class);
        }
    }

    /**
     * Setup by setting configuration + command options into Setting.
     * Setting is a singleton and will be used as generator configuration.
     *
     * @param  string  $connection  The default DB connection name.
     */
    protected function setup(string $connection): void
    {
        $setting = app(Setting::class);
        $setting->setDefaultConnection($connection);
        $setting->setUseDBCollation((bool) $this->option('use-db-collation'));
        $setting->setIgnoreIndexNames((bool) $this->option('default-index-names'));
        $setting->setIgnoreForeignKeyNames((bool) $this->option('default-fk-names'));
        $setting->setSquash((bool) $this->option('squash'));
        $setting->setWithHasTable((bool) $this->option('with-has-table'));

        $setting->setPath(
            $this->option('path') ?? Config::get('migrations-generator.migration_target_path'),
        );

        $this->setStubPath($setting);

        $setting->setDate(
            $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::now(),
        );

        $setting->setTableFilename(
            $this->option('table-filename') ?? Config::get('migrations-generator.filename_pattern.table'),
        );

        $setting->setViewFilename(
            $this->option('view-filename') ?? Config::get('migrations-generator.filename_pattern.view'),
        );

        $setting->setProcedureFilename(
            $this->option('proc-filename') ?? Config::get('migrations-generator.filename_pattern.procedure'),
        );

        $setting->setFkFilename(
            $this->option('fk-filename') ?? Config::get('migrations-generator.filename_pattern.foreign_key'),
        );
    }

    /**
     * Set migration stub.
     */
    protected function setStubPath(Setting $setting): void
    {
        $defaultStub = Config::get('migrations-generator.migration_template_path');

        $setting->setStubPath(
            $this->option('template-path') ?? $defaultStub,
        );
    }

    /**
     * Get all tables from schema or return table list provided in option.
     * Then filter and exclude tables in `--ignore` option if any.
     * Also exclude migrations table
     *
     * @return \Illuminate\Support\Collection<int, string> Filtered table names.
     */
    protected function filterTables(): Collection
    {
        $tables = $this->schema->getTableNames();

        return $this->filterAndExcludeAsset($tables);
    }

    /**
     * Get all views from schema or return view list provided in option.
     * Then filter and exclude tables in `--ignore` option if any.
     * Return empty if `--skip-views`
     *
     * @return \Illuminate\Support\Collection<int, string> Filtered view names.
     */
    protected function filterViews(): Collection
    {
        if ($this->option('skip-views')) {
            return new Collection([]);
        }

        $views = $this->schema->getViewNames();

        return $this->filterAndExcludeAsset($views);
    }

    /**
     * Filter and exclude tables in `--ignore` option if any.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $allAssets  Names before filter.
     * @return \Illuminate\Support\Collection<int, string> Filtered names.
     */
    protected function filterAndExcludeAsset(Collection $allAssets): Collection
    {
        $tables = $allAssets;

        $tableArg = (string) $this->argument('tables');

        if ($tableArg !== '') {
            $tables = $allAssets->intersect(explode(',', $tableArg));
            return $tables->diff($this->getExcludedTables());
        }

        $tableOpt = (string) $this->option('tables');

        if ($tableOpt !== '') {
            $tables = $allAssets->intersect(explode(',', $tableOpt));
            return $tables->diff($this->getExcludedTables());
        }

        return $tables->diff($this->getExcludedTables());
    }

    /**
     * Get a list of tables to be excluded.
     *
     * @return string[]
     */
    protected function getExcludedTables(): array
    {
        $prefix = DB::getTablePrefix();

        // https://github.com/laravel/framework/pull/49330
        $migrationTable = $prefix . (Config::get('database.migrations.table') ?? Config::get('database.migrations'));

        $excludes = [$migrationTable];
        $ignore   = (string) $this->option('ignore');

        if ($ignore !== '') {
            $excludes = array_merge($excludes, explode(',', $ignore));
        }

        if ($this->option('skip-vendor')) {
            $vendorTables = app(Migrator::class)->getVendorTableNames();
            $excludes     = array_merge($excludes, $vendorTables);
        }

        return $excludes;
    }

    /**
     * Asks user for log migration permission.
     *
     * @throws \Exception
     */
    protected function askIfLogMigrationTable(string $defaultConnection): void
    {
        if ($this->skipInput()) {
            return;
        }

        $this->shouldLog = $this->confirm('Do you want to log these migrations in the migrations table?', true);

        if (!$this->shouldLog) {
            return;
        }

        $this->repository->setSource(DB::getName() ?? '');

        if ($defaultConnection !== DB::getName()) {
            if (
                !$this->confirm(
                    'Log into current connection: ' . DB::getName() . '? [Y = ' . DB::getName() . ', n = ' . $defaultConnection . ' (default connection)]',
                    true,
                )
            ) {
                $this->repository->setSource($defaultConnection);
            }
        }

        if (!$this->repository->repositoryExists()) {
            $this->repository->createRepository();
        }

        $this->nextBatchNumber = $this->askInt(
            'Next Batch Number is: ' . $this->repository->getNextBatchNumber() . '. We recommend using Batch Number 0 so that it becomes the "first" migration.',
            0,
        );
    }

    /**
     * Checks if should skip gather input from the user.
     *
     * @throws \Exception
     */
    protected function skipInput(): bool
    {
        if ($this->option('no-interaction') || $this->option('skip-log')) {
            return true;
        }

        if ($this->option('log-with-batch') === null) {
            return false;
        }

        if (!ctype_digit($this->option('log-with-batch'))) {
            throw new Exception('--log-with-batch must be a valid integer.');
        }

        $this->shouldLog       = true;
        $this->nextBatchNumber = (int) $this->option('log-with-batch');

        return true;
    }

    /**
     * Ask user for a Numeric Value, or blank for default.
     *
     * @param  string  $question  Question to ask
     * @param  int|null  $default  Default Value (optional)
     * @return int Answer
     */
    protected function askInt(string $question, ?int $default = null): int
    {
        $ask = 'Your answer needs to be a numeric value';

        if (!is_null($default)) {
            $question .= ' [Default: ' . $default . ']';
            $ask      .= ' or blank for default. [Default: ' . $default . ']';
        }

        $answer = $this->ask($question, (string) $default);

        while (!ctype_digit($answer) && !($answer === '' && !is_null($default))) {
            $answer = $this->ask($ask, (string) $default);
        }

        if ($answer === '') {
            $answer = $default;
        }

        return (int) $answer;
    }

    /**
     * Generates table, view and foreign key migrations.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $tables  Table names.
     * @param  \Illuminate\Support\Collection<int, string>  $views  View names.
     */
    protected function generate(Collection $tables, Collection $views): void
    {
        if (app(Setting::class)->isSquash()) {
            $this->generateSquashedMigrations($tables, $views);
            return;
        }

        $this->generateMigrations($tables, $views);
    }

    /**
     * Generates table, view and foreign key migrations.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $tables  Table names.
     * @param  \Illuminate\Support\Collection<int, string>  $views  View names.
     */
    protected function generateMigrations(Collection $tables, Collection $views): void
    {
        $setting = app(Setting::class);

        $this->info('Setting up Tables and Index migrations.');
        $this->generateTables($tables);

        if (!$this->option('skip-views')) {
            $setting->getDate()->addSecond();
            $this->info("\nSetting up Views migrations.");
            $this->generateViews($views);
        }

        if (!$this->option('skip-proc')) {
            $setting->getDate()->addSecond();
            $this->info("\nSetting up Stored Procedures migrations.");
            $this->generateProcedures();
        }

        $setting->getDate()->addSecond();
        $this->info("\nSetting up Foreign Key migrations.");
        $this->generateForeignKeys($tables);
    }

    /**
     * Generate all migrations in a single file.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $tables
     * @param  \Illuminate\Support\Collection<int, string>  $views
     */
    protected function generateSquashedMigrations(Collection $tables, Collection $views): void
    {
        $this->info('Remove old temporary files if any.');
        $this->squash->cleanTemps();

        $this->info('Setting up Tables and Index migrations.');
        $this->generateTablesToTemp($tables);

        if (!$this->option('skip-views')) {
            $this->info("\nSetting up Views migrations.");
            $this->generateViewsToTemp($views);
        }

        if (!$this->option('skip-proc')) {
            $this->info("\nSetting up Stored Procedure migrations.");
            $this->generateProceduresToTemp();
        }

        $this->info("\nSetting up Foreign Key migrations.");
        $this->generateForeignKeysToTemp($tables);

        $migrationFilepath = $this->squash->squashMigrations();

        $this->info("\nAll migrations squashed.");

        if (!$this->shouldLog) {
            return;
        }

        $this->logMigration($migrationFilepath);
    }

    /**
     * Generates table migrations.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $tables  Table names.
     */
    protected function generateTables(Collection $tables): void
    {
        $tables->each(function (string $table): void {
            $path = $this->tableMigration->write(
                $this->schema->getTable($table),
            );

            $this->info("Created: $path");

            if (!$this->shouldLog) {
                return;
            }

            $this->logMigration($path);
        });
    }

    /**
     * Generates table migrations.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $tables  Table names.
     */
    protected function generateTablesToTemp(Collection $tables): void
    {
        $tables->each(function (string $table): void {
            $this->tableMigration->writeToTemp(
                $this->schema->getTable($table),
            );

            $this->info("Prepared: $table");
        });
    }

    /**
     * Generate view migrations.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $views  View names.
     */
    protected function generateViews(Collection $views): void
    {
        $schemaViews = $this->schema->getViews();
        $schemaViews->each(function (View $view) use ($views): void {
            if (!$views->contains($view->getName())) {
                return;
            }

            $path = $this->viewMigration->write($view);

            $this->info("Created: $path");

            if (!$this->shouldLog) {
                return;
            }

            $this->logMigration($path);
        });
    }

    /**
     * Generate view migrations.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $views  View names.
     */
    protected function generateViewsToTemp(Collection $views): void
    {
        $schemaViews = $this->schema->getViews();
        $schemaViews->each(function (View $view) use ($views): void {
            if (!$views->contains($view->getName())) {
                return;
            }

            $this->viewMigration->writeToTemp($view);

            $this->info('Prepared: ' . $view->getName());
        });
    }

    /**
     * Generate stored procedure migrations.
     */
    protected function generateProcedures(): void
    {
        $procedures = $this->schema->getProcedures();
        $procedures->each(function (Procedure $procedure): void {
            $path = $this->procedureMigration->write($procedure);

            $this->info("Created: $path");

            if (!$this->shouldLog) {
                return;
            }

            $this->logMigration($path);
        });
    }

    /**
     * Generate stored procedure migrations.
     */
    protected function generateProceduresToTemp(): void
    {
        $procedures = $this->schema->getProcedures();
        $procedures->each(function (Procedure $procedure): void {
            $this->procedureMigration->writeToTemp($procedure);

            $this->info('Prepared: ' . $procedure->getName());
        });
    }

    /**
     * Generates foreign key migrations.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $tables  Table names.
     */
    protected function generateForeignKeys(Collection $tables): void
    {
        $tables->each(function (string $table): void {
            $foreignKeys = $this->schema->getForeignKeys($table);

            if (!$foreignKeys->isNotEmpty()) {
                return;
            }

            $path = $this->foreignKeyMigration->write(
                $table,
                $foreignKeys,
            );

            $this->info("Created: $path");

            if (!$this->shouldLog) {
                return;
            }

            $this->logMigration($path);
        });
    }

    /**
     * Generates foreign key migrations.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $tables  Table names.
     */
    protected function generateForeignKeysToTemp(Collection $tables): void
    {
        $tables->each(function (string $table): void {
            $foreignKeys = $this->schema->getForeignKeys($table);

            if (!$foreignKeys->isNotEmpty()) {
                return;
            }

            $this->foreignKeyMigration->writeToTemp(
                $table,
                $foreignKeys,
            );

            $this->info('Prepared: ' . $table);
        });
    }

    /**
     * Logs migration repository.
     */
    protected function logMigration(string $migrationFilepath): void
    {
        $file = basename($migrationFilepath, '.php');
        $this->repository->log($file, $this->nextBatchNumber);
    }

    /**
     * Get DB schema by the database connection name.
     *
     * @throws \Exception
     */
    protected function makeSchema(): Schema
    {
        $driver = DB::getDriverName();

        if (!$driver) {
            throw new Exception('Failed to find database driver.');
        }

        return match ($driver) {
            Driver::MARIADB->value, Driver::MYSQL->value => $this->schema = app(MySQLSchema::class),
            Driver::PGSQL->value => $this->schema                         = app(PgSQLSchema::class),
            Driver::SQLITE->value => $this->schema                        = app(SQLiteSchema::class),
            Driver::SQLSRV->value => $this->schema                        = app(SQLSrvSchema::class),
            default => throw new Exception('The database driver in use is not supported.'),
        };
    }
}
