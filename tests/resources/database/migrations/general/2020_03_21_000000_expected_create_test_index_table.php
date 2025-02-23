<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use KitLoong\MigrationsGenerator\Enum\Driver;
use KitLoong\MigrationsGenerator\Tests\TestMigration;

return new class extends TestMigration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('test_index', function (Blueprint $table) {
            $table->increments('id');
            $table->string('index')->index();
            $table->string('index_custom')->index('index_custom');
            $table->string('col_multi1');
            $table->string('col_multi2');
            $table->string('col_multi_custom1');
            $table->string('col_multi_custom2');
            $table->string('unique')->unique();
            $table->string('unique_custom')->unique('unique_custom');
            $table->string('column-hyphen')->index();
            $table->string('chain')->index();

            $table->index(['col_multi1', 'col_multi2']);
            $table->index(['col_multi_custom1', 'col_multi_custom2'], 'index_multi_custom');
            $table->unique(['col_multi1', 'col_multi2']);
            $table->unique(['col_multi_custom1', 'col_multi_custom2'], 'unique_multi_custom');
            $table->unique('chain');

            // SQLite does not support spatial index.
            if (DB::getDriverName() !== Driver::SQLITE->value) {
                $table->geography('spatial_index', null, 0)->spatialIndex();
                $table->geography('spatial_index_custom', null, 0);
                $table->spatialIndex('spatial_index_custom', 'spatial_index_custom');
            }

            if (in_array(DB::getDriverName(), [Driver::MARIADB->value, Driver::MYSQL->value, Driver::PGSQL->value])) {
                $table->string('fulltext')->fulltext();
                $table->string('fulltext_custom')->fulltext('fulltext_custom');
                $table->fullText(['col_multi1', 'col_multi2']);
                $table->fullText('chain');
            }

            // TODO Laravel 10 does not support `$table->index(DB::raw("with_length(16)"))`
//            if (DB::getDriverName() === Driver::MYSQL->value) {
//                $table->index(['col_multi1', DB::raw('col_multi2(16)')], 'with_length_multi_custom');
//                $table->string('with_length');
//                $table->string('with_length_custom');
//                $table->index([DB::raw('with_length(16)')]);
//                $table->index([DB::raw('with_length_custom(16)')], 'with_length_custom');
//            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('test_index');
    }
};
