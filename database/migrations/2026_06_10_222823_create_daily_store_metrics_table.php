<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BI aggregation layer — one row per (tenant, store, Manila day).
     * Rebuilt nightly by `bi:aggregate-daily` (delete-then-insert), so
     * rows are derived data: safe to wipe and regenerate from sales +
     * expenses at any time.
     */
    public function up(): void
    {
        Schema::create('daily_store_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('store_id');
            $table->date('date');

            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('refunds_total', 15, 2)->default(0);
            $table->decimal('net_sales', 15, 2)->default(0);
            $table->decimal('profit', 15, 2)->default(0);
            $table->decimal('cogs', 15, 2)->default(0);

            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('sc_discount_total', 15, 2)->default(0);
            $table->decimal('pwd_discount_total', 15, 2)->default(0);
            $table->decimal('sp_discount_total', 15, 2)->default(0);
            $table->decimal('naac_discount_total', 15, 2)->default(0);
            $table->decimal('voucher_discount_total', 15, 2)->default(0);

            $table->decimal('vatable_total', 15, 2)->default(0);
            $table->decimal('vat_total', 15, 2)->default(0);
            $table->decimal('non_vat_total', 15, 2)->default(0);
            $table->decimal('zero_rated_total', 15, 2)->default(0);

            $table->decimal('cash_total', 15, 2)->default(0);
            $table->decimal('ewallet_total', 15, 2)->default(0);
            $table->decimal('credit_total', 15, 2)->default(0);
            $table->decimal('bank_transfer_total', 15, 2)->default(0);
            $table->decimal('cheque_total', 15, 2)->default(0);

            $table->decimal('ecommerce_sales_total', 15, 2)->default(0);
            $table->decimal('expenses_total', 15, 2)->default(0);

            $table->unsignedInteger('transactions')->default(0);
            $table->unsignedInteger('refund_count')->default(0);
            $table->unsignedInteger('ecommerce_transactions')->default(0);

            $table->timestamps();

            $table->unique(['user_id', 'store_id', 'date'], 'dsm_user_store_date_unique');
            $table->index(['user_id', 'date'], 'dsm_user_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_store_metrics');
    }
};
