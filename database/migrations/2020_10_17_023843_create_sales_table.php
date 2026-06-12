<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('son');
            $table->string('counter');
            $table->double('total', 15, 2);
            $table->double('cash', 15, 2)->nullable();
            $table->double('change', 15, 2)->nullable();
            $table->double('vatable', 15, 2);
            $table->double('vat', 15, 2);
            $table->double('non_vat', 15, 2);
            $table->double('zero_rated', 15, 2);
            $table->longText('header')->nullable();
            $table->longText('footer')->nullable();
            $table->boolean('type');
            $table->integer('sales_by');
            $table->integer('pos_id')->index('pos_id');
            $table->integer('store_id');
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
        Schema::dropIfExists('sales');
    }
}
