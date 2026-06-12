<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompletedByToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('completed_by')->unsigned()->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('picked_up_at')->nullable();
            $table->integer('sales_id')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('completed_by');
            $table->dropColumn('completed_at');
            $table->dropColumn('picked_up_at');
            $table->dropColumn('sales_id');
        });
    }
}
