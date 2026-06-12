<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransferLinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->double('qty', 10, 2);
            $table->double('received', 15, 2);
            $table->integer('item_id');
            $table->integer('unit_id');
            $table->string('unit');
            $table->double('unit_qty', 15, 2);
            $table->integer('transfer_id');
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
        Schema::dropIfExists('transfer_lines');
    }
}
