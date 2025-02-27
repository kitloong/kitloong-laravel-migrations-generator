<?php

namespace KitLoong\MigrationsGenerator\Tests\Unit\Repositories\Entities\MySQL;

use KitLoong\MigrationsGenerator\Repositories\Entities\MySQL\ShowColumn;
use KitLoong\MigrationsGenerator\Tests\TestCase;

class ShowColumnTest extends TestCase
{
    public function testShowColumn(): void
    {
        // Generate test
        $showColumn = new ShowColumn((object) [
            'Field'   => 'field',
            'Type'    => 'type',
            'Null'    => 'null',
            'Key'     => 'key',
            'Default' => 'default',
            'Extra'   => 'extra',
        ]);

        $this->assertEquals('field', $showColumn->getField());
        $this->assertEquals('type', $showColumn->getType());
        $this->assertEquals('null', $showColumn->getNull());
        $this->assertEquals('key', $showColumn->getKey());
        $this->assertEquals('default', $showColumn->getDefault());
        $this->assertEquals('extra', $showColumn->getExtra());
    }
}
