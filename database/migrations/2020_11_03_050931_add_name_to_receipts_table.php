<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNameToReceiptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('receipts', function (Blueprint $table) {
            //
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone',)->nullable();
            $table->string('ptu',)->nullable();
            $table->string('accredition')->nullable();
            $table->boolean('display');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('receipts', function (Blueprint $table) {
            //
            $table->dropColumn('name');
            $table->dropColumn('email');
            $table->dropColumn('phone');
            $table->dropColumn('ptu');
            $table->dropColumn('accredition');
            $table->dropColumn('display');
        });
    }
}
