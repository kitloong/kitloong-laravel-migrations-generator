<?php

namespace KitLoong\MigrationsGenerator\Database;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use KitLoong\MigrationsGenerator\Schema\Schema;
use KitLoong\MigrationsGenerator\Support\CheckLaravelVersion;
use KitLoong\MigrationsGenerator\Support\TableName;

/**
 * @phpstan-type SchemaTable array{
 *     name: string,
 *     schema: ?string,
 *     schema_qualified_name: string,
 *     size: ?int,
 *     comment: ?string,
 *     collation: ?string,
 *     engine: ?string,
 * }
 *
 * @phpstan-type SchemaView array{
 *     name: string,
 *     schema: ?string,
 *     schema_qualified_name: string,
 *     definition: string,
 * }
 *
 * @phpstan-type SchemaColumn array{
 *     name: string,
 *     type_name: string,
 *     type: string,
 *     collation: ?string,
 *     nullable: bool,
 *     default: ?string,
 *     auto_increment: bool,
 *     comment: ?string,
 *     generation: ?array{type: 'stored'|'virtual', expression: string},
 * }
 *
 * @phpstan-type SchemaIndex array{
 *     name: string,
 *     columns: string[],
 *     type: ?string,
 *     unique: bool,
 *     primary: bool,
 * }
 *
 * @phpstan-type SchemaForeignKey array{
 *     name: ?string,
 *     columns: string[],
 *     foreign_schema: ?string,
 *     foreign_table: string,
 *     foreign_columns: string[],
 *     on_update: string,
 *     on_delete: string,
 * }
 *
 * @phpstan-type SchemaType array{
 *     name: string,
 *     schema: string,
 *     schema_qualified_name: string,
 *     implicit: bool,
 *     type: string,
 *     category: string,
 * }
 */
abstract class DatabaseSchema implements Schema
{
    use CheckLaravelVersion;

    use TableName;

    /**
     * @var array<string, SchemaTable>
     */
    protected array $tables = [];

    /**
     * @inheritDoc
     */
    public function getTableNames(): Collection
    {
        return new Collection(array_column($this->getSchemaTables(), 'name'));
    }

    /**
     * Get a table from the schema by name.
     *
     * @return SchemaTable
     */
    protected function getSchemaTable(string $name): array
    {
        if ($this->tables === []) {
            foreach ($this->getSchemaTables() as $table) {
                /** @var SchemaTable $table */
                $this->tables[$table['name']] = $table;
            }
        }

        return $this->tables[$name];
    }

    /**
     * Get columns from the schema by table name.
     *
     * @return \Illuminate\Support\Collection<int, SchemaColumn>
     */
    protected function getSchemaColumns(string $table): Collection
    {
        if ($this->atLeastLaravel12()) {
            return new Collection(SchemaFacade::getColumns(SchemaFacade::getCurrentSchemaName() . '.' . $this->stripTablePrefix($table)));
        }

        return new Collection(SchemaFacade::getColumns($this->stripTablePrefix($table)));
    }

    /**
     * Get indexes from the schema by table name.
     *
     * @return \Illuminate\Support\Collection<int, SchemaIndex>
     */
    protected function getSchemaIndexes(string $table): Collection
    {
        if ($this->atLeastLaravel12()) {
            return new Collection(SchemaFacade::getIndexes(SchemaFacade::getCurrentSchemaName() . '.' . $this->stripTablePrefix($table)));
        }

        return new Collection(SchemaFacade::getIndexes($this->stripTablePrefix($table)));
    }

    /**
     * Get views from the schema.
     *
     * @return \Illuminate\Support\Collection<int, SchemaView>
     */
    protected function getSchemaViews(): Collection
    {
        if ($this->atLeastLaravel12()) {
            return new Collection(SchemaFacade::getViews(SchemaFacade::getCurrentSchemaName()));
        }

        return new Collection(SchemaFacade::getViews());
    }

    /**
     * Get foreign keys from the schema by table name.
     *
     * @return \Illuminate\Support\Collection<int, SchemaForeignKey>
     */
    protected function getSchemaForeignKeys(string $table): Collection
    {
        if ($this->atLeastLaravel12()) {
            return new Collection(SchemaFacade::getForeignKeys(SchemaFacade::getCurrentSchemaName() . '.' . $this->stripTablePrefix($table)));
        }

        return new Collection(SchemaFacade::getForeignKeys($this->stripTablePrefix($table)));
    }

    /**
     * Get all tables from the schema.
     *
     * @return SchemaTable[]
     */
    protected function getSchemaTables(): array
    {
        if ($this->atLeastLaravel12()) {
            return SchemaFacade::getTables(SchemaFacade::getCurrentSchemaName());
        }

        return SchemaFacade::getTables();
    }

    /**
     * Get user defined types from the schema.
     *
     * @return SchemaType[]
     */
    protected function getSchemaTypes(): array
    {
        if ($this->atLeastLaravel12()) {
            return SchemaFacade::getTypes(SchemaFacade::getCurrentSchemaName());
        }

        return SchemaFacade::getTypes();
    }
}
