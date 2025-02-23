<?php

namespace KitLoong\MigrationsGenerator\Migration;

use Illuminate\Support\Collection;
use KitLoong\MigrationsGenerator\Enum\Migrations\Method\SchemaBuilder;
use KitLoong\MigrationsGenerator\Migration\Blueprint\SchemaBlueprint;
use KitLoong\MigrationsGenerator\Migration\Blueprint\TableBlueprint;
use KitLoong\MigrationsGenerator\Migration\Enum\MigrationFileType;
use KitLoong\MigrationsGenerator\Migration\Generator\ForeignKeyGenerator;
use KitLoong\MigrationsGenerator\Migration\Writer\MigrationWriter;
use KitLoong\MigrationsGenerator\Migration\Writer\SquashWriter;
use KitLoong\MigrationsGenerator\Setting;
use KitLoong\MigrationsGenerator\Support\MigrationNameHelper;
use KitLoong\MigrationsGenerator\Support\TableName;

class ForeignKeyMigration
{
    use TableName;

    public function __construct(
        private readonly ForeignKeyGenerator $foreignKeyGenerator,
        private readonly MigrationNameHelper $migrationNameHelper,
        private readonly MigrationWriter $migrationWriter,
        private readonly Setting $setting,
        private readonly SquashWriter $squashWriter,
    ) {
    }

    /**
     * Create foreign key migration.
     *
     * @param  \Illuminate\Support\Collection<int, covariant \KitLoong\MigrationsGenerator\Schema\Models\ForeignKey>  $foreignKeys
     * @return string The migration file path.
     */
    public function write(string $table, Collection $foreignKeys): string
    {
        $up   = $this->up($table, $foreignKeys);
        $down = $this->down($table, $foreignKeys);

        $this->migrationWriter->writeTo(
            $path = $this->makeMigrationPath($table),
            $this->setting->getStubPath(),
            $this->makeMigrationClassName($table),
            new Collection([$up]),
            new Collection([$down]),
            MigrationFileType::FOREIGN_KEY,
        );

        return $path;
    }

    /**
     * Write foreign key migration into temporary file.
     *
     * @param  \Illuminate\Support\Collection<int, covariant \KitLoong\MigrationsGenerator\Schema\Models\ForeignKey>  $foreignKeys
     */
    public function writeToTemp(string $table, Collection $foreignKeys): void
    {
        $up   = $this->up($table, $foreignKeys);
        $down = $this->down($table, $foreignKeys);

        $this->squashWriter->writeToTemp(new Collection([$up]), new Collection([$down]));
    }

    /**
     * Generates `up` schema for foreign key.
     *
     * @param  \Illuminate\Support\Collection<int, covariant \KitLoong\MigrationsGenerator\Schema\Models\ForeignKey>  $foreignKeys
     */
    private function up(string $table, Collection $foreignKeys): SchemaBlueprint
    {
        $up          = $this->getSchemaBlueprint($table);
        $upBlueprint = new TableBlueprint();

        foreach ($foreignKeys as $foreignKey) {
            $method = $this->foreignKeyGenerator->generate($foreignKey);
            $upBlueprint->setMethod($method);
        }

        $up->setBlueprint($upBlueprint);

        return $up;
    }

    /**
     * Generates `down` schema for foreign key.
     *
     * @param  \Illuminate\Support\Collection<int, covariant \KitLoong\MigrationsGenerator\Schema\Models\ForeignKey>  $foreignKeys
     */
    private function down(string $table, Collection $foreignKeys): SchemaBlueprint
    {
        $down          = $this->getSchemaBlueprint($table);
        $downBlueprint = new TableBlueprint();

        foreach ($foreignKeys as $foreignKey) {
            $method = $this->foreignKeyGenerator->generateDrop($foreignKey);
            $downBlueprint->setMethod($method);
        }

        $down->setBlueprint($downBlueprint);

        return $down;
    }

    /**
     * Makes class name for foreign key migration.
     *
     * @param  string  $table  Table name.
     */
    private function makeMigrationClassName(string $table): string
    {
        $withoutPrefix = $this->stripTablePrefix($table);
        return $this->migrationNameHelper->makeClassName(
            $this->setting->getFkFilename(),
            $withoutPrefix,
        );
    }

    /**
     * Makes file path for foreign key migration.
     *
     * @param  string  $table  Table name.
     */
    private function makeMigrationPath(string $table): string
    {
        $withoutPrefix = $this->stripTablePrefix($table);
        return $this->migrationNameHelper->makeFilename(
            $this->setting->getFkFilename(),
            $this->setting->getDateForMigrationFilename(),
            $withoutPrefix,
        );
    }

    private function getSchemaBlueprint(string $table): SchemaBlueprint
    {
        return new SchemaBlueprint(
            $table,
            SchemaBuilder::TABLE,
        );
    }
}
