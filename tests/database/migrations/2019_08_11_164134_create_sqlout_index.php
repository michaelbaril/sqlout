<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSqloutIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('scout.sqlout.table_name'), function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('record_type', 191)->index();
            $table->unsignedBigInteger('record_id')->index();
            $table->string('field', 191)->index();
            $table->unsignedSmallInteger('weight')->default(1);
            $table->text('content');
            $table->timestamps();
        });
        $tableName = DB::getTablePrefix() . config('scout.sqlout.table_name');
        DB::statement("ALTER TABLE $tableName ADD FULLTEXT searchindex_content (content)");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(config('scout.sqlout.table_name'));
    }
}
