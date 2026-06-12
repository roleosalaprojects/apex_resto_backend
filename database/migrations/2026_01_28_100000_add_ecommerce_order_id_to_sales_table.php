<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('ecommerce_order_id')->nullable()->after('bank_id');
            $table->foreign('ecommerce_order_id')->references('id')->on('ecommerce_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['ecommerce_order_id']);
            $table->dropColumn('ecommerce_order_id');
        });
    }
};
