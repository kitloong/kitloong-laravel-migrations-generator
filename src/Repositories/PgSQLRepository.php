<?php

namespace KitLoong\MigrationsGenerator\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use KitLoong\MigrationsGenerator\Repositories\Entities\PgSQL\IndexDefinition;
use KitLoong\MigrationsGenerator\Repositories\Entities\ProcedureDefinition;

class PgSQLRepository extends Repository
{
    /**
     * Get constraint by table and column name.
     *
     * @param  string  $table  Table name.
     * @param  string  $column  Column name.
     */
    public function getCheckConstraintDefinition(string $table, string $column): ?string
    {
        $searchPath = DB::connection()->getConfig('search_path') ?: DB::connection()->getConfig('schema');

        $result = DB::selectOne(
            "SELECT pgc.conname AS constraint_name,
                       pgc.contype,
                       ccu.table_schema AS table_schema,
                       ccu.table_name,
                       ccu.column_name,
                       pg_get_constraintdef(pgc.oid) AS definition
                FROM pg_constraint pgc
                JOIN pg_namespace nsp ON nsp.oid = pgc.connamespace
                JOIN pg_class  cls ON pgc.conrelid = cls.oid
                LEFT JOIN information_schema.constraint_column_usage ccu
                          ON pgc.conname = ccu.constraint_name
                          AND nsp.nspname = ccu.constraint_schema
                WHERE contype ='c'
                    AND nsp.nspname = '$searchPath'
                    AND ccu.table_name='$table'
                    AND ccu.column_name='$column'",
        );
        return $result?->definition;
    }

    /**
     * Get a list of fulltext indexes.
     *
     * @param  string  $table  Table name.
     * @return \Illuminate\Support\Collection<int, \KitLoong\MigrationsGenerator\Repositories\Entities\PgSQL\IndexDefinition>
     */
    public function getFulltextIndexes(string $table): Collection
    {
        $columns     = DB::select(
            "SELECT tablename,
                       indexname,
                       indexdef
                FROM pg_indexes
                WHERE tablename = '$table'
                    AND indexdef LIKE '%to_tsvector(%'
                ORDER BY indexname",
        );
        $definitions = new Collection();

        if (count($columns) > 0) {
            foreach ($columns as $column) {
                $definitions->push(
                    new IndexDefinition(
                        $column->tablename,
                        $column->indexname,
                        $column->indexdef,
                    ),
                );
            }
        }

        return $definitions;
    }

    /**
     * Get a list of stored procedures.
     *
     * @return \Illuminate\Support\Collection<int, \KitLoong\MigrationsGenerator\Repositories\Entities\ProcedureDefinition>
     */
    public function getProcedures(): Collection
    {
        $list = new Collection();

        $searchPath = DB::connection()->getConfig('search_path') ?: DB::connection()->getConfig('schema');

        $procedures = DB::select(
            "SELECT proname, pg_get_functiondef(pg_proc.oid) AS definition
            FROM pg_catalog.pg_proc
                JOIN pg_namespace ON pg_catalog.pg_proc.pronamespace = pg_namespace.oid
            WHERE prokind = 'p'
                AND pg_namespace.nspname = '$searchPath'",
        );

        foreach ($procedures as $procedure) {
            if ($procedure->definition === null || $procedure->definition === '') {
                continue;
            }

            $definition = str_replace('$procedure', '$', $procedure->definition);
            $list->push(new ProcedureDefinition($procedure->proname, $definition));
        }

        return $list;
    }
}
