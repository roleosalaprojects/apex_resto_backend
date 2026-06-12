<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdjustmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('adjustments', function (Blueprint $table) {
            $table->id();
            $table->integer('so');
            $table->integer('store_id');
            $table->double('total', 15, 2);
            $table->double('received', 15, 2);
            $table->string('reason');
            $table->text('note')->nullable();
            $table->integer('created_by');
            $table->integer('updated_by')->nullable();
            $table->integer('received_by')->nullable();
            $table->boolean('status');
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
        Schema::dropIfExists('adjustments');
    }
}
