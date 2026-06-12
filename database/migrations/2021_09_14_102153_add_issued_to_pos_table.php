<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIssuedToPosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pos', function (Blueprint $table) {
            //
            $table->string('ptu', 255)->nullable();
            $table->string('issued', 255)->nullable();
            $table->string('expiry', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pos', function (Blueprint $table) {
            //
            $table->dropColumn('ptu');
            $table->dropColumn('issued');
            $table->dropColumn('expiry');
        });
    }
}
