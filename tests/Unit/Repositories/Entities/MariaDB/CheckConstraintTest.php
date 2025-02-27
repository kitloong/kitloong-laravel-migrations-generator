<?php

namespace KitLoong\MigrationsGenerator\Tests\Unit\Repositories\Entities\MariaDB;

use KitLoong\MigrationsGenerator\Repositories\Entities\MariaDB\CheckConstraint;
use KitLoong\MigrationsGenerator\Tests\TestCase;

class CheckConstraintTest extends TestCase
{
    public function testCheckConstraint(): void
    {
        $checkConstraint = new CheckConstraint((object) [
            'CONSTRAINT_CATALOG' => 'def', // should convert to lowercase
            'constraint_schema'  => 'schema',
            'table_name'         => 'table',
            'constraint_name'    => 'name',
            'level'              => 'level',
            'check_clause'       => 'clause',
        ]);

        $this->assertEquals('def', $checkConstraint->getConstraintCatalog());
        $this->assertEquals('schema', $checkConstraint->getConstraintSchema());
        $this->assertEquals('table', $checkConstraint->getTableName());
        $this->assertEquals('name', $checkConstraint->getConstraintName());
        $this->assertEquals('level', $checkConstraint->getLevel());
        $this->assertEquals('clause', $checkConstraint->getCheckClause());
    }
}
