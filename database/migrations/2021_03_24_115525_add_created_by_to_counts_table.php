<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCreatedByToCountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('counts', function (Blueprint $table) {
            //
            $table->boolean('status');
            $table->integer("ic");
            $table->integer("created_by");
            $table->integer("user_id");
            $table->integer("total");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('counts', function (Blueprint $table) {
//            $table->dropColumn('status');
//            $table->dropColumn('ic');
//            $table->dropColumn('created_by');
//            $table->dropColumn('"user_id"');
//            $table->dropColumn('total');
        });
    }
}
