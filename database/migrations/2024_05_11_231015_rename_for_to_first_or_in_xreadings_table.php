<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameForToFirstOrInXreadingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('xreadings', function (Blueprint $table) {
            $table->renameColumn('for', 'first_or');
            $table->renameColumn('lor', 'last_or');
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
            $table->renameColumn('first_or', 'for');
            $table->renameColumn('last_or', 'lor');
        });
    }
}
