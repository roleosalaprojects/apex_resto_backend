<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransfersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->integer('to');
            $table->integer('source_store');
            $table->integer('destination_store');
            $table->double('total', 15, 2);
            $table->double('received', 15, 2);
            $table->string('note')->nullable();
            $table->integer('created_by');
            $table->integer('updated_by')->nullable();
            $table->integer('received_by')->nullable();
            $table->integer('status');
            $table->integer('user_id');
            $table->dateTime('received_at')->nullable();
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
        Schema::dropIfExists('transfers');
    }
}
