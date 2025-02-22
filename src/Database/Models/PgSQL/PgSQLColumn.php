<?php

namespace KitLoong\MigrationsGenerator\Database\Models\PgSQL;

use Illuminate\Support\Str;
use KitLoong\MigrationsGenerator\Database\Models\DatabaseColumn;
use KitLoong\MigrationsGenerator\Enum\Migrations\Method\ColumnType;
use KitLoong\MigrationsGenerator\Repositories\PgSQLRepository;
use KitLoong\MigrationsGenerator\Support\Regex;

class PgSQLColumn extends DatabaseColumn
{
    use PgSQLParser;

    private PgSQLRepository $repository;

    /**
     * @inheritDoc
     */
    public function __construct(string $table, array $column)
    {
        parent::__construct($table, $column);

        $this->default = $this->parseDefault($column['default'], $this->type);
        $this->default = $this->escapeDefault($this->default);

        $this->repository = app(PgSQLRepository::class);

        $this->setTypeToIncrements(false);

        switch ($this->type) {
            case ColumnType::DATE:
            case ColumnType::DATETIME:
            case ColumnType::DATETIME_TZ:
            case ColumnType::TIME:
            case ColumnType::TIME_TZ:
            case ColumnType::TIMESTAMP:
            case ColumnType::TIMESTAMP_TZ:
            case ColumnType::SOFT_DELETES:
            case ColumnType::SOFT_DELETES_TZ:
                $this->setRawDefault();
                break;

            case ColumnType::GEOGRAPHY:
            case ColumnType::GEOMETRY:
                $this->setRealSpatialColumn($column['type']);
                break;

            case ColumnType::STRING:
                $this->presetValues = $this->getEnumPresetValues();

                if (count($this->presetValues) > 0) {
                    $this->type = ColumnType::ENUM;
                }

                break;

            default:
        }

        $this->setStoredDefinition();
    }

    /**
     * @inheritDoc
     */
    protected function getColumnType(string $type): ColumnType
    {
        return PgSQLColumnType::toColumnType($type);
    }

    /**
     * @inheritDoc
     */
    protected function escapeDefault(?string $default): ?string
    {
        if ($default === null) {
            return null;
        }

        if (preg_match('/\((.?)\)\)/', $default)) {
            return $default;
        }

        return parent::escapeDefault($default);
    }

    protected function setTypeToIncrements(bool $supportUnsigned): void
    {
        parent::setTypeToIncrements($supportUnsigned);

        if ($this->default === null) {
            return;
        }

        if (!Str::startsWith($this->default, 'nextval(') || !Str::endsWith($this->default, '::regclass)')) {
            return;
        }

        $this->default = null;
    }

    /**
     * Check and set to use raw default.
     * Raw default will be generated with DB::raw().
     */
    private function setRawDefault(): void
    {
        if ($this->default === null) {
            return;
        }

        if ($this->default === 'now()') {
            $this->rawDefault = true;
            return;
        }

        // If default value is expression, eg: timezone('Europe/Rome'::text, now())
        if (!preg_match('/\((.?)\)/', $this->default)) {
            return;
        }

        $this->rawDefault = true;
    }

    /**
     * Set to geometry type base on geography map.
     */
    private function setRealSpatialColumn(string $fullDefinitionType): void
    {
        $dataType = strtolower($fullDefinitionType);
        $dataType = preg_replace('/\s+/', '', $dataType);

        if ($dataType === null) {
            return;
        }

        $dotPosition = Str::position($dataType, '.');

        if ($dotPosition !== false) {
            $dataType = Str::substr($dataType, $dotPosition + 1);
        }

        if ($dataType === 'geography' || $dataType === 'geometry') {
            return;
        }

        if (!preg_match('/(\w+)(?:\((\w+)(?:,\s*(\w+))?\))?/', $dataType, $matches) || !isset($matches[2])) {
            return;
        }

        $spatialSubType = $matches[2];
        $spatialSrID    = isset($matches[3]) ? (int) $matches[3] : null;

        $this->spatialSubType = $spatialSubType;
        $this->spatialSrID    = $spatialSrID;
    }

    /**
     * Get the preset values if the column is "enum".
     *
     * @return string[]
     */
    private function getEnumPresetValues(): array
    {
        $definition = $this->repository->getCheckConstraintDefinition($this->tableName, $this->name);

        if ($definition === null) {
            return [];
        }

        if ($definition === '') {
            return [];
        }

        $presetValues = Regex::getTextBetweenAll($definition, "'", "'::");

        if ($presetValues === null) {
            return [];
        }

        return $presetValues;
    }

    /**
     * Set stored definition if the column is stored.
     */
    private function setStoredDefinition(): void
    {
        $this->storedDefinition = $this->repository->getStoredDefinition($this->tableName, $this->name);

        // A generated column cannot have a column default or an identity definition.
        if ($this->storedDefinition === null) {
            return;
        }

        $this->default = null;
    }
}
