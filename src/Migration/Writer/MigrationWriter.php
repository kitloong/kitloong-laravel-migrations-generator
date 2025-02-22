<?php

namespace KitLoong\MigrationsGenerator\Migration\Writer;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use KitLoong\MigrationsGenerator\Migration\Blueprint\WritableBlueprint;
use KitLoong\MigrationsGenerator\Migration\Enum\MigrationFileType;
use KitLoong\MigrationsGenerator\Migration\Enum\Space;

class MigrationWriter
{
    public function __construct(private readonly MigrationStub $migrationStub)
    {
    }

    /**
     * Writes migration to destination.
     *
     * @param  string  $path  Migration file destination path.
     * @param  string  $stubPath  Migration stub file path.
     * @param  \Illuminate\Support\Collection<int, covariant \KitLoong\MigrationsGenerator\Migration\Blueprint\WritableBlueprint>  $up  Blueprint of migration `up`.
     * @param  \Illuminate\Support\Collection<int, covariant \KitLoong\MigrationsGenerator\Migration\Blueprint\WritableBlueprint>  $down  Blueprint of migration `down`.
     */
    public function writeTo(
        string $path,
        string $stubPath,
        string $className,
        Collection $up,
        Collection $down,
        MigrationFileType $migrationFileType,
    ): void {
        try {
            $stub = $this->migrationStub->getStub($stubPath);

            $upString   = $this->prettifyToString($up);
            $downString = $this->prettifyToString($down);

            $useDBFacade = false;

            if (Str::contains($upString . $downString, 'DB::')) {
                $useDBFacade = true;
            }

            $use = implode(Space::LINE_BREAK->value, $this->getNamespaces($migrationFileType, $useDBFacade));

            // Create directory if it doesn't exist
            $directory = dirname($path);

            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            File::put(
                $path,
                $this->migrationStub->populateStub($stub, $use, $className, $upString, $downString),
            );
        } catch (FileNotFoundException) {
            // Do nothing.
        }
    }

    /**
     * @return string[]
     */
    private function getNamespaces(MigrationFileType $migrationFileType, bool $useDBFacade): array
    {
        if (
            $migrationFileType === MigrationFileType::VIEW
            || $migrationFileType === MigrationFileType::PROCEDURE
        ) {
            return [
                'use Illuminate\Database\Migrations\Migration;',
                'use Illuminate\Support\Facades\DB;',
            ];
        }

        $imports = [
            'use Illuminate\Database\Migrations\Migration;',
            'use Illuminate\Database\Schema\Blueprint;',
        ];

        if ($useDBFacade) {
            $imports[] = 'use Illuminate\Support\Facades\DB;';
        }

        // Push at the last to maintain alphabetically sort.
        $imports[] = 'use Illuminate\Support\Facades\Schema;';

        return $imports;
    }

    /**
     * Convert collection of blueprints to string and prettify and tabular.
     *
     * @param  \Illuminate\Support\Collection<int, covariant \KitLoong\MigrationsGenerator\Migration\Blueprint\WritableBlueprint>  $blueprints
     */
    private function prettifyToString(Collection $blueprints): string
    {
        return $blueprints->map(static fn (WritableBlueprint $blueprint) => $blueprint->toString())->implode(Space::LINE_BREAK->value . Space::TAB->value . Space::TAB->value); // Add tab to prettify
    }
}
