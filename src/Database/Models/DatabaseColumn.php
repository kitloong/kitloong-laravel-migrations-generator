<?php

namespace KitLoong\MigrationsGenerator\Database\Models;

use KitLoong\MigrationsGenerator\Enum\Migrations\ColumnName;
use KitLoong\MigrationsGenerator\Enum\Migrations\Method\ColumnType;
use KitLoong\MigrationsGenerator\Schema\Models\Column;

/**
 * @phpstan-import-type SchemaColumn from \KitLoong\MigrationsGenerator\Database\DatabaseSchema
 */
abstract class DatabaseColumn implements Column
{
    private const REMEMBER_TOKEN_LENGTH = 100;

    protected bool $autoincrement;

    protected ?string $charset = null;

    protected ?string $collation = null;

    protected ?string $comment = null;

    protected ?string $default = null;

    protected ?int $length = null;

    protected string $name;

    protected bool $notNull;

    protected bool $onUpdateCurrentTimestamp;

    protected ?int $precision = null;

    /**
     * @var string[]
     */
    protected array $presetValues;

    protected bool $rawDefault;

    protected int $scale;

    protected string $tableName;

    protected ColumnType $type;

    protected bool $unsigned = false;

    protected ?string $virtualDefinition = null;

    protected ?string $storedDefinition = null;

    protected ?string $spatialSubType = null;

    protected ?int $spatialSrID = null;

    /**
     * Get ColumnType by type name.
     */
    abstract protected function getColumnType(string $type): ColumnType;

    /**
     * @param  SchemaColumn  $column
     */
    public function __construct(string $table, array $column)
    {
        $this->tableName                 = $table;
        $this->name                      = $column['name'];
        $this->type                      = $this->getColumnType($column['type_name']);
        $this->length                    = $this->parseLength($column['type']);
        [$this->precision, $this->scale] = $this->parsePrecisionAndScale($column['type']);
        $this->comment                   = $this->escapeComment($column['comment']);
        $this->notNull                   = !$column['nullable'];
        $this->collation                 = $column['collation'] !== null && $column['collation'] !== '' ? $column['collation'] : null;
        $this->charset                   = null;
        $this->autoincrement             = $column['auto_increment'];
        $this->presetValues              = [];
        $this->onUpdateCurrentTimestamp  = false;
        $this->rawDefault                = false;
        $this->virtualDefinition         = null;
        $this->storedDefinition          = null;
        $this->spatialSubType            = null;
        $this->spatialSrID               = null;

        $this->setTypeToSoftDeletes();
        $this->setTypeToRememberToken();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @inheritDoc
     */
    public function getType(): ColumnType
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    public function getLength(): ?int
    {
        return $this->length;
    }

    /**
     * @inheritDoc
     */
    public function getScale(): int
    {
        return $this->scale;
    }

    /**
     * @inheritDoc
     */
    public function getPrecision(): ?int
    {
        return $this->precision;
    }

    /**
     * @inheritDoc
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @inheritDoc
     */
    public function isUnsigned(): bool
    {
        return $this->unsigned;
    }

    /**
     * @inheritDoc
     */
    public function isNotNull(): bool
    {
        return $this->notNull;
    }

    /**
     * @inheritDoc
     */
    public function getDefault(): ?string
    {
        return $this->default;
    }

    /**
     * @inheritDoc
     */
    public function getCollation(): ?string
    {
        return $this->collation;
    }

    /**
     * @inheritDoc
     */
    public function getCharset(): ?string
    {
        return $this->charset;
    }

    /**
     * @inheritDoc
     */
    public function isAutoincrement(): bool
    {
        return $this->autoincrement;
    }

    /**
     * @inheritDoc
     */
    public function getPresetValues(): array
    {
        return $this->presetValues;
    }

    /**
     * @inheritDoc
     */
    public function getSpatialSubType(): ?string
    {
        return $this->spatialSubType;
    }

    /**
     * @inheritDoc
     */
    public function getSpatialSrID(): ?int
    {
        return $this->spatialSrID;
    }

    /**
     * @inheritDoc
     */
    public function isOnUpdateCurrentTimestamp(): bool
    {
        return $this->onUpdateCurrentTimestamp;
    }

    /**
     * @inheritDoc
     */
    public function isRawDefault(): bool
    {
        return $this->rawDefault;
    }

    /**
     * @inheritDoc
     */
    public function getVirtualDefinition(): ?string
    {
        return $this->virtualDefinition;
    }

    /**
     * @inheritDoc
     */
    public function getStoredDefinition(): ?string
    {
        return $this->storedDefinition;
    }

    /**
     * Set the column type to "increments" or "*Increments" if the column is auto increment.
     * If the DB supports unsigned, should check if the column is unsigned.
     *
     * @param  bool  $supportUnsigned  DB support unsigned integer.
     */
    protected function setTypeToIncrements(bool $supportUnsigned): void
    {
        if (
            !in_array($this->type, [
                ColumnType::BIG_INTEGER,
                ColumnType::INTEGER,
                ColumnType::MEDIUM_INTEGER,
                ColumnType::SMALL_INTEGER,
                ColumnType::TINY_INTEGER,
            ])
        ) {
            return;
        }

        if (!$this->autoincrement) {
            return;
        }

        if ($supportUnsigned && !$this->unsigned) {
            return;
        }

        if ($this->type === ColumnType::INTEGER) {
            $this->type = ColumnType::INCREMENTS;
            return;
        }

        $this->type = ColumnType::from(str_replace('Integer', 'Increments', $this->type->value));
    }

    /**
     * Escape `'` with `''`.
     */
    protected function escapeDefault(?string $default): ?string
    {
        if ($default === null) {
            return null;
        }

        $default = str_replace("'", "''", $default);
        return addcslashes($default, '\\');
    }

    /**
     * Escape `\` with `\\`.
     */
    protected function escapeComment(?string $comment): ?string
    {
        if ($comment === null || $comment === '') {
            return null;
        }

        return addcslashes($comment, '\\');
    }

    /**
     * Parse the length from the full definition type.
     */
    protected function parseLength(string $fullDefinitionType): ?int
    {
        switch ($this->type) {
            case ColumnType::CHAR:
            case ColumnType::STRING:
            case ColumnType::DATE:
            case ColumnType::DATETIME:
            case ColumnType::DATETIME_TZ:
            case ColumnType::TIME:
            case ColumnType::TIME_TZ:
            case ColumnType::TIMESTAMP:
            case ColumnType::TIMESTAMP_TZ:
                if (preg_match('/\((\d*)\)/', $fullDefinitionType, $matches) === 1) {
                    return (int) $matches[1];
                }

                break;

            default:
        }

        return null;
    }

    /**
     * Parse the precision and scale from the full definition type.
     *
     * @return array{0: int|null, 1: int}
     */
    protected function parsePrecisionAndScale(string $fullDefinitionType): array
    {
        switch ($this->type) {
            case ColumnType::DECIMAL:
            case ColumnType::DOUBLE:
            case ColumnType::FLOAT:
                if (preg_match('/\((\d+)(?:,\s*(\d+))?\)?/', $fullDefinitionType, $matches) === 1) {
                    return [(int) $matches[1], isset($matches[2]) ? (int) $matches[2] : 0];
                }

                break;

            default:
        }

        return [null, 0];
    }

    /**
     * Set the column type to "softDeletes" or "softDeletesTz".
     */
    private function setTypeToSoftDeletes(): void
    {
        if ($this->name !== ColumnName::DELETED_AT->value) {
            return;
        }

        switch ($this->type) {
            case ColumnType::TIMESTAMP:
                $this->type = ColumnType::SOFT_DELETES;
                return;

            case ColumnType::TIMESTAMP_TZ:
                $this->type = ColumnType::SOFT_DELETES_TZ;
                return;

            default:
        }
    }

    /**
     * Set the column type to "rememberToken".
     */
    private function setTypeToRememberToken(): void
    {
        if (
            ColumnName::REMEMBER_TOKEN->value !== $this->name
            || $this->length !== self::REMEMBER_TOKEN_LENGTH
        ) {
            return;
        }

        $this->type = ColumnType::REMEMBER_TOKEN;
    }
}
