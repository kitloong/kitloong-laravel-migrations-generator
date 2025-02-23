<?php

namespace KitLoong\MigrationsGenerator\Database\Models;

use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Support\Facades\Schema;
use KitLoong\MigrationsGenerator\Support\CheckLaravelVersion;

class Blueprint extends BaseBlueprint
{
    use CheckLaravelVersion;

    public function __construct(string $table)
    {
        if ($this->atLeastLaravel12()) {
            parent::__construct(Schema::getConnection(), $table);

            return;
        }

        parent::__construct($table); // @phpstan-ignore-line
    }

    /**
     * @return string[]
     */
    public function toSqlWithCompatible(): array
    {
        if ($this->atLeastLaravel12()) {
            return parent::toSql();
        }

        return parent::toSql(Schema::getConnection(), Schema::getConnection()->getSchemaGrammar()); // @phpstan-ignore-line
    }
}
