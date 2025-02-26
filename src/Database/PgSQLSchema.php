<?php

namespace KitLoong\MigrationsGenerator\Database;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use KitLoong\MigrationsGenerator\Database\Models\PgSQL\PgSQLForeignKey;
use KitLoong\MigrationsGenerator\Database\Models\PgSQL\PgSQLProcedure;
use KitLoong\MigrationsGenerator\Database\Models\PgSQL\PgSQLTable;
use KitLoong\MigrationsGenerator\Database\Models\PgSQL\PgSQLView;
use KitLoong\MigrationsGenerator\Repositories\Entities\ProcedureDefinition;
use KitLoong\MigrationsGenerator\Repositories\PgSQLRepository;
use KitLoong\MigrationsGenerator\Schema\Models\Table;
use KitLoong\MigrationsGenerator\Schema\Models\View;
use KitLoong\MigrationsGenerator\Support\CheckLaravelVersion;

class PgSQLSchema extends DatabaseSchema
{
    use CheckLaravelVersion;

    /**
     * @var \Illuminate\Support\Collection<int, string>
     */
    private Collection $userDefinedTypes;

    private bool $ranGetUserDefinedTypes = false;

    public function __construct(private readonly PgSQLRepository $pgSQLRepository)
    {
        $this->userDefinedTypes = new Collection();
    }

    /**
     * @inheritDoc
     */
    public function getTableNames(): Collection
    {
        return (new Collection($this->getSchemaTables()))
            ->filter(static function (array $table): bool {
                if ($table['name'] === 'spatial_ref_sys') {
                    return false;
                }

                // Schema name defined in the framework configuration.
                $searchPath = DB::connection()->getConfig('search_path') ?: DB::connection()->getConfig('schema');

                return $table['schema'] === $searchPath;
            })
            ->map(static fn (array $table): string => $table['name'])
            ->values();
    }

    /**
     * @inheritDoc
     */
    public function getTable(string $name): Table
    {
        return new PgSQLTable(
            $this->getSchemaTable($name),
            $this->getSchemaColumns($name),
            $this->getSchemaIndexes($name),
            $this->getUserDefinedTypes(),
        );
    }

    /**
     * @inheritDoc
     */
    public function getViewNames(): Collection
    {
        return $this->getViews()->map(static fn (View $view) => $view->getName());
    }

    /**
     * @inheritDoc
     */
    public function getViews(): Collection
    {
        return $this->getSchemaViews()
            ->filter(static function (array $view) {
                if (in_array($view['name'], ['geography_columns', 'geometry_columns'])) {
                    return false;
                }

                // Start from Laravel 9, the `schema` configuration option used to configure Postgres connection search paths renamed to `search_path`.
                // Fallback to `schema` if Laravel version is older than 9.
                $searchPath = DB::connection()->getConfig('search_path') ?: DB::connection()->getConfig('schema');

                return $view['schema'] === $searchPath;
            })
            ->map(static fn (array $view) => new PgSQLView($view))
            ->values();
    }

    /**
     * @inheritDoc
     */
    public function getProcedures(): Collection
    {
        return $this->pgSQLRepository->getProcedures()
            ->map(static fn (ProcedureDefinition $procedureDefinition) => new PgSQLProcedure($procedureDefinition->getName(), $procedureDefinition->getDefinition()));
    }

    /**
     * @inheritDoc
     */
    public function getForeignKeys(string $table): Collection
    {
        return $this->getSchemaForeignKeys($table)
            ->map(static fn (array $foreignKey) => new PgSQLForeignKey($table, $foreignKey));
    }

    /**
     * Get user defined types from the schema.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function getUserDefinedTypes(): Collection
    {
        if (!$this->ranGetUserDefinedTypes) {
            $this->userDefinedTypes       = new Collection(array_column($this->getSchemaTypes(), 'name'));
            $this->ranGetUserDefinedTypes = true;
        }

        return $this->userDefinedTypes;
    }
}
