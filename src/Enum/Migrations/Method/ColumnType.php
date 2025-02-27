<?php

namespace KitLoong\MigrationsGenerator\Enum\Migrations\Method;

/**
 * Define column types of the framework.
 *
 * @link https://laravel.com/docs/master/migrations#available-column-types
 */
enum ColumnType: string implements MethodName
{
    case BIG_INTEGER             = 'bigInteger';
    case BIG_INCREMENTS          = 'bigIncrements';
    case BINARY                  = 'binary';
    case BOOLEAN                 = 'boolean';
    case CHAR                    = 'char';
    case DATE                    = 'date';
    case DATETIME                = 'dateTime';
    case DATETIME_TZ             = 'dateTimeTz';
    case DECIMAL                 = 'decimal';
    case DOUBLE                  = 'double';
    case ENUM                    = 'enum';
    case FLOAT                   = 'float';
    case GEOGRAPHY               = 'geography';
    case GEOMETRY                = 'geometry';
    case INCREMENTS              = 'increments';
    case INTEGER                 = 'integer';
    case IP_ADDRESS              = 'ipAddress';
    case JSON                    = 'json';
    case JSONB                   = 'jsonb';
    case LONG_TEXT               = 'longText';
    case MAC_ADDRESS             = 'macAddress';
    case MEDIUM_INCREMENTS       = 'mediumIncrements';
    case MEDIUM_INTEGER          = 'mediumInteger';
    case MEDIUM_TEXT             = 'mediumText';
    case REMEMBER_TOKEN          = 'rememberToken';
    case SET                     = 'set';
    case SMALL_INCREMENTS        = 'smallIncrements';
    case SMALL_INTEGER           = 'smallInteger';
    case SOFT_DELETES            = 'softDeletes';
    case SOFT_DELETES_TZ         = 'softDeletesTz';
    case STRING                  = 'string';
    case TEXT                    = 'text';
    case TIME                    = 'time';
    case TIME_TZ                 = 'timeTz';
    case TIMESTAMP               = 'timestamp';
    case TIMESTAMPS              = 'timestamps';
    case TIMESTAMP_TZ            = 'timestampTz';
    case TIMESTAMPS_TZ           = 'timestampsTz';
    case TINY_INCREMENTS         = 'tinyIncrements';
    case TINY_INTEGER            = 'tinyInteger';
    case TINY_TEXT               = 'tinyText';
    case UNSIGNED_BIG_INTEGER    = 'unsignedBigInteger';
    case UNSIGNED_INTEGER        = 'unsignedInteger';
    case UNSIGNED_MEDIUM_INTEGER = 'unsignedMediumInteger';
    case UNSIGNED_SMALL_INTEGER  = 'unsignedSmallInteger';
    case UNSIGNED_TINY_INTEGER   = 'unsignedTinyInteger';
    case UUID                    = 'uuid';
    case YEAR                    = 'year';
}
