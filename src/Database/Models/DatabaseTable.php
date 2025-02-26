<?php

namespace KitLoong\MigrationsGenerator\Database\Models;

use Illuminate\Support\Collection;
use KitLoong\MigrationsGenerator\Schema\Models\Column;
use KitLoong\MigrationsGenerator\Schema\Models\Index;
use KitLoong\MigrationsGenerator\Schema\Models\Table;
use KitLoong\MigrationsGenerator\Schema\Models\UDTColumn;

/**
 * @phpstan-import-type SchemaColumn from \KitLoong\MigrationsGenerator\Database\DatabaseSchema
 * @phpstan-import-type SchemaTable from \KitLoong\MigrationsGenerator\Database\DatabaseSchema
 * @phpstan-import-type SchemaIndex from \KitLoong\MigrationsGenerator\Database\DatabaseSchema
 */
abstract class DatabaseTable implements Table
{
    protected ?string $collation = null;

    /**
     * @var \Illuminate\Support\Collection<int, \KitLoong\MigrationsGenerator\Schema\Models\Column>
     */
    protected Collection $columns;

    /**
     * @var \Illuminate\Support\Collection<int, \KitLoong\MigrationsGenerator\Schema\Models\UDTColumn>
     */
    protected Collection $udtColumns;

    protected string $name;

    protected ?string $comment = null;

    /**
     * @var \Illuminate\Support\Collection<int, \KitLoong\MigrationsGenerator\Schema\Models\Index>
     */
    protected Collection $indexes;

    /**
     * Make a Column instance.
     *
     * @param  SchemaColumn  $column
     */
    abstract protected function makeColumn(string $table, array $column): Column;

    /**
     * Make a UDTColumn instance.
     *
     * @param  SchemaColumn  $column
     */
    abstract protected function makeUDTColumn(string $table, array $column): UDTColumn;

    /**
     * Make an Index instance.
     *
     * @param  SchemaIndex  $index
     */
    abstract protected function makeIndex(string $table, array $index, bool $hasUDTColumn): Index;

    /**
     * Create a new instance.
     *
     * @param  SchemaTable  $table
     * @param  \Illuminate\Support\Collection<int, SchemaColumn>  $columns
     * @param  \Illuminate\Support\Collection<int, SchemaIndex>  $indexes
     * @param  \Illuminate\Support\Collection<int, string>  $userDefinedTypes
     */
    public function __construct(array $table, Collection $columns, Collection $indexes, Collection $userDefinedTypes)
    {
        $this->name      = $table['name'];
        $this->comment   = $table['comment'];
        $this->collation = $table['collation'];

        $this->columns = $columns->reduce(function (Collection $columns, array $column) use ($userDefinedTypes) {
            if (!$userDefinedTypes->contains($column['type_name'])) {
                $columns->push($this->makeColumn($this->name, $column));
            }

            return $columns;
        }, new Collection())->values();

        $this->udtColumns = $columns->reduce(function (Collection $columns, array $column) use ($userDefinedTypes) {
            if ($userDefinedTypes->contains($column['type_name'])) {
                $columns->push($this->makeUDTColumn($this->name, $column));
            }

            return $columns;
        }, new Collection())->values();

        $this->indexes = $indexes->map(function (array $index) {
            $hasUdtColumn = $this->udtColumns
                ->map(static fn ($column) => $column->getName())
                ->intersect($index['columns'])
                ->isNotEmpty();

            return $this->makeIndex($this->name, $index, $hasUdtColumn);
        })->values();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @inheritDoc
     */
    public function getColumns(): Collection
    {
        return $this->columns;
    }

    /**
     * @inheritDoc
     */
    public function getUdtColumns(): Collection
    {
        return $this->udtColumns;
    }

    /**
     * @inheritDoc
     */
    public function getIndexes(): Collection
    {
        return $this->indexes;
    }

    /**
     * @inheritDoc
     */
    public function getCollation(): ?string
    {
        return $this->collation;
    }
}
