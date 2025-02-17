<?php

namespace KitLoong\MigrationsGenerator\Database\Models\PgSQL;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use KitLoong\MigrationsGenerator\Database\Models\DatabaseUDTColumn;
use KitLoong\MigrationsGenerator\Enum\Migrations\Method\ColumnType;
use KitLoong\MigrationsGenerator\Support\TableName;

class PgSQLUDTColumn extends DatabaseUDTColumn
{
    use PgSQLParser;

    use TableName;

    /**
     * @inheritDoc
     */
    public function __construct(string $table, array $column)
    {
        parent::__construct($table, $column);

        $blueprint = new Blueprint(Schema::getConnection(), $this->stripTablePrefix($table));

        // Generate the add column statement with string column type.
        $blueprint->addColumn('string', $column['name'], [
            'autoIncrement' => $column['auto_increment'],
            'collation'     => $column['collation'],
            'comment'       => $column['comment'],
            'default'       => $this->parseDefault($column['default'], ColumnType::STRING), // Assume is string
            'nullable'      => $column['nullable'],
        ]);

        $sqls = $blueprint->toSql();

        // Replace the string column type with the user-defined type.
        $sqls[0] = Str::replaceFirst(' varchar ', ' ' . $column['type'] . ' ', $sqls[0]);

        $this->sqls = $sqls;
    }
}
