<?php

namespace KitLoong\MigrationsGenerator\Database\Models\SQLSrv;

use KitLoong\MigrationsGenerator\Database\Models\DatabaseColumn;
use KitLoong\MigrationsGenerator\Enum\Migrations\Method\ColumnType;
use KitLoong\MigrationsGenerator\Repositories\SQLSrvRepository;

class SQLSrvColumn extends DatabaseColumn
{
    use SQLSrvParser;

    // Recognise scale = 3 and length = 8 as 0 precision.
    private const DATETIME_EMPTY_SCALE  = 3;
    private const DATETIME_EMPTY_LENGTH = 8;

    // Recognise scale = 7 and length = 10 as 0 precision.
    private const DATETIME_TZ_EMPTY_SCALE  = 7;
    private const DATETIME_TZ_EMPTY_LENGTH = 10;

    private SQLSrvRepository $repository;

    /**
     * @inheritDoc
     */
    public function __construct(string $table, array $column)
    {
        parent::__construct($table, $column);

        $this->default = $this->parseDefault($column['default']);
        $this->default = $this->escapeDefault($this->default);

        $this->repository = app(SQLSrvRepository::class);

        $this->setTypeToIncrements(false);
        $this->fixMoneyPrecision($column['type_name']);

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
                $this->length = $this->getDateTimeLength();
                break;

            case ColumnType::CHAR:
            case ColumnType::STRING:
            case ColumnType::TEXT:
                $this->presetValues = $this->getEnumPresetValues();

                if (count($this->presetValues) > 0) {
                    $this->type = ColumnType::ENUM;
                    break;
                }

                if ($this->isText($column['type'])) {
                    $this->type   = ColumnType::TEXT;
                    $this->length = null;
                }

                $this->fixLength($column['type_name']);

                break;

            default:
        }
    }

    /**
     * @inheritDoc
     */
    protected function getColumnType(string $type): ColumnType
    {
        return SQLSrvColumnType::toColumnType($type);
    }

    /**
     * Get the datetime column length.
     */
    private function getDateTimeLength(): ?int
    {
        $columnDef = $this->repository->getColumnDefinition($this->tableName, $this->name);

        if ($columnDef === null) {
            return null;
        }

        switch ($this->type) {
            case ColumnType::DATETIME:
                if (
                    $columnDef->getScale() === self::DATETIME_EMPTY_SCALE
                    && $columnDef->getLength() === self::DATETIME_EMPTY_LENGTH
                ) {
                    return null;
                }

                return $columnDef->getScale();

            case ColumnType::DATETIME_TZ:
                if (
                    $columnDef->getScale() === self::DATETIME_TZ_EMPTY_SCALE
                    && $columnDef->getLength() === self::DATETIME_TZ_EMPTY_LENGTH
                ) {
                    return null;
                }

                return $columnDef->getScale();

            default:
                return $columnDef->getScale();
        }
    }

    /**
     * Set precision to 19 and scale to 4.
     */
    private function fixMoneyPrecision(string $dbType): void
    {
        if ($dbType === 'money') {
            $this->precision = 19;
            $this->scale     = 4;
            return;
        }

        if ($dbType !== 'smallmoney') {
            return;
        }

        $this->precision = 10;
        $this->scale     = 4;
    }

    /**
     * Check if the column type is "text".
     */
    private function isText(string $fullDefinitionType): bool
    {
        return $fullDefinitionType === 'nvarchar(max)' || $fullDefinitionType === 'varchar(max)';
    }

    /**
     * Get the preset values if the column is `enum`.
     *
     * @return string[]
     */
    private function getEnumPresetValues(): array
    {
        return $this->repository->getEnumPresetValues(
            $this->tableName,
            $this->name,
        )->all();
    }

    /**
     * Fix the unicode string length.
     */
    private function fixLength(string $dbType): void
    {
        if ($this->length === null) {
            return;
        }

        switch ($dbType) {
            case 'nchar':
            case 'ntext':
            case 'nvarchar':
                // Unicode data requires 2 bytes per character
                $this->length /= 2;
                return;

            default:
        }
    }
}
