<?php

namespace KitLoong\MigrationsGenerator\Database\Models\SQLSrv;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use KitLoong\MigrationsGenerator\Database\Models\DatabaseUDTColumn;
use KitLoong\MigrationsGenerator\Support\TableName;

class SQLSrvUDTColumn extends DatabaseUDTColumn
{
    use SQLSrvParser;

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
            'default'       => $this->parseDefault($column['default']),
            'nullable'      => $column['nullable'],
        ]);

        $sqls = $blueprint->toSql();

        // Replace the string column type with the user-defined type.
        $sqls[0] = Str::replaceFirst(' nvarchar() ', ' ' . $column['type'] . ' ', $sqls[0]);

        $this->sqls = $sqls;
    }
}
