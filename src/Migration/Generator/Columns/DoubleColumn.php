<?php

namespace KitLoong\MigrationsGenerator\Migration\Generator\Columns;

use KitLoong\MigrationsGenerator\Enum\Migrations\Method\ColumnModifier;
use KitLoong\MigrationsGenerator\Migration\Blueprint\Method;
use KitLoong\MigrationsGenerator\Schema\Models\Column;
use KitLoong\MigrationsGenerator\Schema\Models\Table;

class DoubleColumn implements ColumnTypeGenerator
{
    /**
     * @inheritDoc
     */
    public function generate(Table $table, Column $column): Method
    {
        $method = new Method($column->getType(), $column->getName());

        if ($column->isUnsigned()) {
            $method->chain(ColumnModifier::UNSIGNED);
        }

        return $method;
    }
}
