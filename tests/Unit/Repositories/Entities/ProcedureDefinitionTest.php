<?php

namespace KitLoong\MigrationsGenerator\Tests\Unit\Repositories\Entities;

use KitLoong\MigrationsGenerator\Repositories\Entities\ProcedureDefinition;
use KitLoong\MigrationsGenerator\Tests\TestCase;

class ProcedureDefinitionTest extends TestCase
{
    public function testProcedureDefinition(): void
    {
        $procedureDefinition = new ProcedureDefinition('name', 'definition');

        $this->assertEquals('name', $procedureDefinition->getName());
        $this->assertEquals('definition', $procedureDefinition->getDefinition());
    }
}
