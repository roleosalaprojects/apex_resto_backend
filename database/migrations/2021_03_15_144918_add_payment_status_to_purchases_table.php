<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentStatusToPurchasesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchases', function (Blueprint $table) {
            //
            $table->boolean('payment_status')->nullable();
            $table->integer('payment_type')->nullable();
            $table->date('date_issued')->nullable();
            $table->string('cheque_no')->nullable();
            $table->string('issued_to')->nullable();
            $table->string('issued_by')->nullable();
            $table->double('amount',15 ,2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchases', function (Blueprint $table) {
            //
            $table->dropColumn('payment_status');
            $table->dropColumn('payment_type');
            $table->dropColumn('date_issued');
            $table->dropColumn('cheque_no');
            $table->dropColumn('issued_to');
            $table->dropColumn('issued_by');
            $table->dropColumn('amount');
        });
    }
}
