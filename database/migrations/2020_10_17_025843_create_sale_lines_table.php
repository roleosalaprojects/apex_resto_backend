<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaleLinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->double('qty', 15, 2);
            $table->string('unit');
            $table->double('discount', 15, 2);
            $table->double('price', 15, 2);
            $table->double('sub_total', 15, 2);
            $table->double('vatable', 15, 2)->nullable();
            $table->double('vat', 15, 2)->nullable();
            $table->double('exempt', 15, 2)->nullable();
            $table->double('zero_rated', 15, 2)->nullable();
            $table->double('cost', 15, 2);
            $table->double('refundable', 15, 2);
            $table->double('refunded', 15, 2);
            $table->integer('item_id')->index('item_id');
            $table->integer('unit_id')->nullable();
            $table->integer('unit_qty');
            $table->integer('discount_id')->nullable();
            $table->integer('discount_by')->nullable();
            $table->integer('sales_id')->index('sales_id')->unsigned()->nullable()->default(12);

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
        Schema::dropIfExists('sale_lines');
    }
}
