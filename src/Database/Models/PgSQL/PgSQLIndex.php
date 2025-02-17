<?php

namespace KitLoong\MigrationsGenerator\Database\Models\PgSQL;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use KitLoong\MigrationsGenerator\Database\Models\DatabaseIndex;
use KitLoong\MigrationsGenerator\Enum\Migrations\Method\IndexType;
use KitLoong\MigrationsGenerator\Support\TableName;

class PgSQLIndex extends DatabaseIndex
{
    use TableName;

    /**
     * @inheritDoc
     */
    public function __construct(string $table, array $index, bool $hasUDTColumn)
    {
        parent::__construct($table, $index);

        switch ($this->type) {
            case IndexType::PRIMARY:
                // Reset name to empty to indicate use the database platform naming.
                $this->name = '';
                break;

            default:
        }

        if (!$hasUDTColumn) {
            return;
        }

        $blueprint = new Blueprint(Schema::getConnection(), $this->stripTablePrefix($table));

        // Generate the alter index statement.
        $blueprint->{$this->type->value}($this->columns, $this->name);

        $this->udtColumnSqls = $blueprint->toSql();
    }
}
