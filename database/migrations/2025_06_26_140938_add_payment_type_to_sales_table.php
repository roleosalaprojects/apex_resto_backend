<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Payment Types: 1 - Cash, 2 - EWallet
            $table->integer('payment_type')->default(1)->nullable();
            $table->string('reference_number')->nullable();
            $table->double('bank_amount', 15, 2)->nullable();
            $table->integer('bank_id')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('payment_type');
            $table->dropColumn('reference_number');
            $table->dropColumn('bank_amount');
            $table->dropColumn('bank_id');
        });
    }
};
