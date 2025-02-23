<?php

namespace KitLoong\MigrationsGenerator\Migration\Writer;

use Illuminate\Support\Facades\File;

class MigrationStub
{
    /**
     * Get the migration stub file.
     *
     * @return string File content.
     */
    public function getStub(string $stubPath): string
    {
        return File::get($stubPath);
    }

    /**
     * Populates the place-holders in the migration stub.
     *
     * @param  string  $stub  File content.
     * @param  string  $upContent  Content for migration `up`.
     * @param  string  $downContent  Content for migration `down`.
     * @return string Migration content.
     */
    public function populateStub(
        string $stub,
        string $use,
        string $className,
        string $upContent,
        string $downContent,
    ): string {
        $content = $stub;
        $replace = [
            '{{ use }}'   => $use,
            '{{ class }}' => $className,
            '{{ up }}'    => $upContent,
            '{{ down }}'  => $downContent,
        ];
        return str_replace(array_keys($replace), $replace, $content);
    }
}
