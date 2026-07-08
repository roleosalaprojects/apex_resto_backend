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
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            // One row per tender on a multi-tender sale (sales.payment_type
            // = Sale::PAYMENT_MULTI). Amounts are APPLIED amounts — change
            // never lands in a row — so SUM(amount) per sale equals
            // sales.total, which keeps the per-tender X/Z reading buckets
            // reconciling against gross sales.
            $table->unsignedBigInteger('sales_id')->index();
            $table->unsignedTinyInteger('payment_type');
            $table->decimal('amount', 12, 2);
            $table->string('reference_number')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
