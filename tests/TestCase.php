<?php

namespace KitLoong\MigrationsGenerator\Tests;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use KitLoong\MigrationsGenerator\MigrationsGeneratorServiceProvider;
use Orchestra\Testbench\TestCase as Testbench;
use PHPUnit\Framework\Constraint\IsEqual;

abstract class TestCase extends Testbench
{
    /**
     * Asserts that the contents of one file is equal to the contents of another file, ignore the ordering.
     * Also, strip all end of line commas.
     */
    public static function assertFileEqualsIgnoringOrder(string $expected, string $actual, string $message = ''): void
    {
        static::assertFileExists($expected, $message);
        static::assertFileExists($actual, $message);

        $removeLastComma = static fn (string $line): string => Str::endsWith($line, ',' . PHP_EOL)
                ? Str::replaceLast(',' . PHP_EOL, PHP_EOL, $line)
                : $line;

        $expectedFiles   = file($expected) ?: [];
        $expectedContent = new Collection($expectedFiles);
        $expectedContent = $expectedContent->map($removeLastComma)->sort();

        $constraint = new IsEqual($expectedContent->values());

        $actualFiles   = file($actual) ?: [];
        $actualContent = new Collection($actualFiles);
        $actualContent = $actualContent->map($removeLastComma)->sort();

        static::assertThat($actualContent->values(), $constraint, $message);
    }

    /**
     * @inheritDoc
     */
    protected function getPackageProviders($app)
    {
        return [
            MigrationsGeneratorServiceProvider::class,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        app()->setBasePath(__DIR__ . '/../');
    }
}
