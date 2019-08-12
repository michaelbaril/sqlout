<?php

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
        Schema::create('searchindex', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('record_type', 100)->index();
            $table->unsignedBigInteger('record_id')->index();
            $table->string('field');
            $table->unsignedSmallInteger('weight')->default(1);
            $table->text('content');
            $table->timestamps();
            $table->engine = 'MyISAM';
        });
        DB::statement('ALTER TABLE wp_searchindex ADD FULLTEXT searchindex_content (content)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
