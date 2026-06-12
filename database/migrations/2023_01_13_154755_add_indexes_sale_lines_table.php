<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesSaleLinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sale_lines', function(Blueprint $table){
            $table->index(['created_at']);
            $table->index(['item_id']);
            $table->index(['unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sale_lines', function(Blueprint $table){
            $table->dropIndex(['created_at']);
            $table->dropIndex(['item_id']);
            $table->dropIndex(['unit_id']);
        });
    }
}
