<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BI aggregation layer — one row per (tenant, store, item, Manila
     * day). Rebuilt nightly by `bi:aggregate-daily`; derived data.
     * qty_sold uses qty * unit_qty (base-unit quantity), matching
     * ReportService::getSoldItems().
     */
    public function up(): void
    {
        Schema::create('daily_item_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('item_id');
            $table->date('date');

            $table->decimal('qty_sold', 15, 2)->default(0);
            $table->decimal('revenue', 15, 2)->default(0);
            $table->decimal('cost_total', 15, 2)->default(0);
            $table->decimal('profit', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('refund_qty', 15, 2)->default(0);
            $table->decimal('refund_total', 15, 2)->default(0);

            $table->unsignedInteger('transactions')->default(0);

            $table->timestamps();

            $table->unique(['user_id', 'store_id', 'item_id', 'date'], 'dim_user_store_item_date_unique');
            $table->index(['user_id', 'date'], 'dim_user_date_index');
            $table->index(['item_id', 'date'], 'dim_item_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_item_metrics');
    }
};
