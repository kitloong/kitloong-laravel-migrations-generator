<?php

namespace KitLoong\MigrationsGenerator\Tests\Unit\Database\Models\PgSQL;

use KitLoong\MigrationsGenerator\Database\Models\PgSQL\PgSQLColumn;
use KitLoong\MigrationsGenerator\Enum\Migrations\Method\ColumnType;
use KitLoong\MigrationsGenerator\Repositories\PgSQLRepository;
use KitLoong\MigrationsGenerator\Tests\TestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class PgSQLColumnTest extends TestCase
{
    #[DataProvider('spatialTypeNameProvider')]
    public function testSpatialTypeName(string $type): void
    {
        $this->mock(PgSQLRepository::class, static function (MockInterface $mock): void {
            $mock->shouldReceive('getStoredDefinition');
        });

        $column = new PgSQLColumn('table', [
            'name'           => 'column',
            'type_name'      => 'geography',
            'type'           => $type,
            'collation'      => null,
            'nullable'       => false,
            'default'        => null,
            'auto_increment' => false,
            'comment'        => null,
            'generation'     => null,
        ]);

        $this->assertSame(ColumnType::GEOGRAPHY, $column->getType());
        $this->assertSame('point', $column->getSpatialSubType());
        $this->assertSame(4326, $column->getSpatialSrID());
    }

    /**
     * @return array<string, string[]>
     */
    public static function spatialTypeNameProvider(): array
    {
        return [
            'with dot'    => ['extensions.geography(Point,4326)'],
            'without dot' => ['geography(Point,4326)'],
        ];
    }
}
