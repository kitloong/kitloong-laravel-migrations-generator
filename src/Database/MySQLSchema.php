<?php

namespace KitLoong\MigrationsGenerator\Database;

use Illuminate\Support\Collection;
use KitLoong\MigrationsGenerator\Database\Models\MySQL\MySQLForeignKey;
use KitLoong\MigrationsGenerator\Database\Models\MySQL\MySQLProcedure;
use KitLoong\MigrationsGenerator\Database\Models\MySQL\MySQLTable;
use KitLoong\MigrationsGenerator\Database\Models\MySQL\MySQLView;
use KitLoong\MigrationsGenerator\Repositories\Entities\ProcedureDefinition;
use KitLoong\MigrationsGenerator\Repositories\MySQLRepository;
use KitLoong\MigrationsGenerator\Schema\Models\Table;
use KitLoong\MigrationsGenerator\Schema\Models\View;
use KitLoong\MigrationsGenerator\Schema\MySQLSchema as MySQLSchemaInterface;

class MySQLSchema extends DatabaseSchema implements MySQLSchemaInterface
{
    public function __construct(private readonly MySQLRepository $mySQLRepository)
    {
    }

    /**
     * @inheritDoc
     */
    public function getTable(string $name): Table
    {
        return new MySQLTable(
            $this->getSchemaTable($name),
            $this->getSchemaColumns($name),
            $this->getSchemaIndexes($name),
            new Collection(),
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
        return $this->getSchemaViews()->map(static fn (array $view) => new MySQLView($view));
    }

    /**
     * @inheritDoc
     */
    public function getProcedures(): Collection
    {
        return $this->mySQLRepository->getProcedures()
            ->map(static fn (ProcedureDefinition $procedureDefinition) => new MySQLProcedure($procedureDefinition->getName(), $procedureDefinition->getDefinition()));
    }

    /**
     * @inheritDoc
     */
    public function getForeignKeys(string $table): Collection
    {
        return $this->getSchemaForeignKeys($table)
            ->map(static fn (array $foreignKey) => new MySQLForeignKey($table, $foreignKey));
    }
}
