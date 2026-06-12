<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAcceptedByToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('accepted_by')->unsigned()->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->integer('assigned_by')->unsigned()->nullable();
            $table->dateTime('assigned_at')->nullable();
            $table->integer('prepared_by')->unsigned()->nullable();
            $table->dateTime('prepared_at')->nullable();
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
            $table->dropColumn('accepted_by');
            $table->dropColumn('accepted_at');
            $table->dropColumn('assigned_by');
            $table->dropColumn('assigned_at');
            $table->dropColumn('prepared_by');
            $table->dropColumn('prepared_at');
        });
    }
}
