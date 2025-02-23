<?php

namespace KitLoong\MigrationsGenerator\Database;

use Illuminate\Support\Collection;
use KitLoong\MigrationsGenerator\Database\Models\SQLite\SQLiteForeignKey;
use KitLoong\MigrationsGenerator\Database\Models\SQLite\SQLiteTable;
use KitLoong\MigrationsGenerator\Database\Models\SQLite\SQLiteView;
use KitLoong\MigrationsGenerator\Schema\Models\Table;
use KitLoong\MigrationsGenerator\Schema\Models\View;

class SQLiteSchema extends DatabaseSchema
{
    /**
     * @inheritDoc
     */
    public function getTable(string $name): Table
    {
        return new SQLiteTable(
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
        return $this->getSchemaViews()->map(static fn (array $view) => new SQLiteView($view));
    }

    /**
     * @inheritDoc
     */
    public function getProcedures(): Collection
    {
        // Stored procedure does not available.
        // https://sqlite.org/forum/info/78a60bdeec7c1ee9
        return new Collection();
    }

    /**
     * @inheritDoc
     */
    public function getForeignKeys(string $table): Collection
    {
        return $this->getSchemaForeignKeys($table)
            ->map(static fn (array $foreignKey) => new SQLiteForeignKey($table, $foreignKey));
    }
}
