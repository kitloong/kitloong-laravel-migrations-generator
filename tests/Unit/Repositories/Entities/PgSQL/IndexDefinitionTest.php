<?php

namespace KitLoong\MigrationsGenerator\Tests\Unit\Repositories\Entities\PgSQL;

use KitLoong\MigrationsGenerator\Repositories\Entities\PgSQL\IndexDefinition;
use KitLoong\MigrationsGenerator\Tests\TestCase;

class IndexDefinitionTest extends TestCase
{
    public function testIndexDefinition(): void
    {
        // Generate test
        $indexDefinition = new IndexDefinition('table', 'name', 'def');

        $this->assertEquals('table', $indexDefinition->getTableName());
        $this->assertEquals('name', $indexDefinition->getIndexName());
        $this->assertEquals('def', $indexDefinition->getIndexDef());
    }
}
