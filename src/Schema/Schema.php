<?php

namespace KitLoong\MigrationsGenerator\Schema;

use Illuminate\Support\Collection;
use KitLoong\MigrationsGenerator\Schema\Models\Table;

interface Schema
{
    /**
     * Get a list of table names.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getTableNames(): Collection;

    /**
     * Get a table by name.
     *
     * @param  string  $name  Table name.
     */
    public function getTable(string $name): Table;

    /**
     * Get a list of view names.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getViewNames(): Collection;

    /**
     * Get a list of views.
     *
     * @return \Illuminate\Support\Collection<int, covariant \KitLoong\MigrationsGenerator\Schema\Models\View>
     */
    public function getViews(): Collection;

    /**
     * Get a list of foreign keys.
     *
     * @return \Illuminate\Support\Collection<int, covariant \KitLoong\MigrationsGenerator\Schema\Models\ForeignKey>
     */
    public function getForeignKeys(string $table): Collection;

    /**
     * Get a list of store procedures.
     *
     * @return \Illuminate\Support\Collection<int, covariant \KitLoong\MigrationsGenerator\Schema\Models\Procedure>
     */
    public function getProcedures(): Collection;
}
