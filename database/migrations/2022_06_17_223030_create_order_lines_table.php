<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderLinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->double('qty', 15, 2)->nullable();
            $table->double('price', 15, 2)->nullable();
            $table->text('unit_name')->nullable();
            $table->text('item_name')->nullable();
            $table->double('discount', 15, 2)->nullable();
            $table->double('sub_total', 15, 2)->nullable();
            $table->double('unit_qty', 15, 2)->nullable();
            $table->double('cost', 15, 2)->nullable();
            $table->integer('vat_type')->unsigned()->nullable();
            $table->integer('item_id')->unsigned()->nullable();
            $table->integer('unit_id')->unsigned()->nullable();
            $table->integer('discount_by')->unsigned()->nullable();
            $table->integer('discount_id')->unsigned()->nullable();
            $table->integer('tax_id')->unsigned()->nullable();
            $table->double('rate', 15, 2)->nullable();
            $table->text('discount_type')->nullable();
            $table->double('pwd_rate', 15, 2)->nullable();
            $table->double('sc_rate', 15, 2)->nullable();
            $table->boolean('discountable')->nullable();
            $table->integer('type')->unsigned()->nullable();
            $table->integer('order_id')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_lines');
    }
}
