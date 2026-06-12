<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('pos');
            $table->boolean('rfnd');
            $table->boolean('discounts');
            $table->boolean('bck_offc');
            $table->boolean('sls');
            $table->boolean('itms');
            $table->boolean('itms_read');
            $table->boolean('itms_create');
            $table->boolean('itms_update');
            $table->boolean('itms_delete');
            $table->boolean('adjstmnts');
            $table->boolean('adjstmnts_read');
            $table->boolean('adjstmnts_create');
            $table->boolean('adjstmnts_update');
            $table->boolean('adjstmnts_delete');
            $table->boolean('trnsfrs');
            $table->boolean('trnsfrs_read');
            $table->boolean('trnsfrs_create');
            $table->boolean('trnsfrs_update');
            $table->boolean('trnsfrs_delete');
            $table->boolean('emplys');
            $table->boolean('emplys_read');
            $table->boolean('emplys_create');
            $table->boolean('emplys_update');
            $table->boolean('emplys_delete');
            $table->boolean('rl');
            $table->boolean('rl_read');
            $table->boolean('rl_create');
            $table->boolean('rl_update');
            $table->boolean('rl_delete');
            $table->boolean('cstmr');
            $table->boolean('cstmr_read');
            $table->boolean('cstmr_create');
            $table->boolean('cstmr_update');
            $table->boolean('cstmr_delete');
            $table->boolean('str');
            $table->boolean('str_read');
            $table->boolean('str_create');
            $table->boolean('str_update');
            $table->boolean('str_delete');
            $table->boolean('tax');
            $table->boolean('tax_read');
            $table->boolean('tax_create');
            $table->boolean('tax_update');
            $table->boolean('tax_delete');
            $table->boolean('sttngs');
            $table->boolean('status');
            $table->integer('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('roles');
    }
}
