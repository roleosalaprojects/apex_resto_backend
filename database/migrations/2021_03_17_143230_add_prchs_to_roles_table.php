<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPrchsToRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('roles', function (Blueprint $table) {
            //
            $table->boolean('prchs')->nullable();
            $table->boolean('prchs_read')->nullable();
            $table->boolean('prchs_create')->nullable();
            $table->boolean('prchs_update')->nullable();
            $table->boolean('prchs_delete')->nullable();
            $table->boolean('invntry')->nullable();
            $table->boolean('invntry_read')->nullable();
            $table->boolean('invntry_create')->nullable();
            $table->boolean('invntry_update')->nullable();
            $table->boolean('invntry_delete')->nullable();
            $table->boolean('spplrs')->nullable();
            $table->boolean('spplrs_read')->nullable();
            $table->boolean('spplrs_create')->nullable();
            $table->boolean('spplrs_update')->nullable();
            $table->boolean('spplrs_delete')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('roles', function (Blueprint $table) {
            //
        });
    }
}
