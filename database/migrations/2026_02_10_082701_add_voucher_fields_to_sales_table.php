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
            $table->unsignedBigInteger('voucher_id')->nullable()->after('ecommerce_order_id');
            $table->string('voucher_code', 50)->nullable()->after('voucher_id');
            $table->decimal('voucher_discount', 10, 2)->default(0)->after('voucher_code');

            $table->foreign('voucher_id')->references('id')->on('vouchers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->dropColumn(['voucher_id', 'voucher_code', 'voucher_discount']);
        });
    }
};
