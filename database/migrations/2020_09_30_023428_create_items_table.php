<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('barcode');
            $table->string('name');
            $table->integer('category_id')->nullable();
            $table->integer('vatable')->nullable();
            $table->integer('tax_id')->nullable();
            $table->integer('markup')->nullable();
            $table->double('cost', 15, 2);
            $table->double('prev_cost', 15, 2);
            $table->double('price', 15, 2);
            $table->double('prev_price', 15, 2);
            $table->integer('senior');
            $table->integer('pwd');
            $table->boolean('status');
            $table->integer('user_id')->index('user_id');
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
        Schema::dropIfExists('items');
    }
}
