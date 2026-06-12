<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExcessToXreadingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('xreadings', function (Blueprint $table) {
            $table->double('excess_vat', 15, 2)->nullable();
            $table->double('excess_non_vat', 15, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('xreadings', function (Blueprint $table) {
            $table->dropColumn('excess_vat');
            $table->dropColumn('excess_non_vat');
        });
    }
}
