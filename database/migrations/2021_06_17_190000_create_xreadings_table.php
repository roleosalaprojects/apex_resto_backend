<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateXreadingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('xreadings', function (Blueprint $table) {
            $table->id();
            $table->integer('counter')->nullable();
            $table->double('cash', 15, 2)->nullable()->default(0);
            $table->double('vatable', 15, 2)->nullable()->default(0);
            $table->double('refunds', 15, 2)->nullable()->default(0);
            $table->double('vat', 15, 2)->nullable()->default(0);
            $table->double('vat_exempt', 15, 2)->nullable()->default(0);
            $table->double('zero_rated', 15, 2)->nullable()->default(0);
            $table->double('current_sales', 15, 2)->nullable()->default(0);
            $table->double('less_refunds', 15, 2)->nullable()->default(0);
            $table->integer('transactions')->nullable()->default(0);
            $table->double('sc_discounts', 15, )->nullable()->default(0);
            $table->double('pwd_discounts', 15, 2)->nullable()->default(0);
            $table->double('reg_discounts', 15, 2)->nullable()->default(0);
            $table->double('net_sales', 15, 2)->nullable()->default(0);
            $table->integer('generated_by')->nullable();
            $table->string('for', 255)->nullable();
            $table->string('lor', 255)->nullable();
            $table->integer('pos_id')->nullable()->index('pos_id');
            $table->integer('store_id')->nullable();
            $table->integer('user_id')->nullable()->index('user_id');
            // Dates created_at & updated_at
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
        Schema::dropIfExists('xreadings');
    }
}
