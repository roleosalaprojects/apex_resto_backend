<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddScDiscountToSalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->double('sc_discount', 15, 2)->nullable();
            $table->double('pwd_discount', 15, 2)->nullable();
            $table->double('vat_special_discounts', 15, 2)->nullable();
            $table->double('refunds', 15, 2)->nullable();
            $table->string('sale_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            //
            $table->dropColumn('sc_discount');
            $table->dropColumn('pwd_discount');
            $table->dropColumn('vat_special_discounts');
            $table->dropColumn('refunds');
            $table->dropColumn('sale_type');
        });
    }
}
