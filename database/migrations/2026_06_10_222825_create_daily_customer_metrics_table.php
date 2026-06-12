<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BI aggregation layer — one row per (tenant, customer, Manila
     * day). Walk-in sales (customer_id NULL) are intentionally absent;
     * their totals live in daily_store_metrics. Rebuilt nightly by
     * `bi:aggregate-daily`; derived data.
     */
    public function up(): void
    {
        Schema::create('daily_customer_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('customer_id');
            $table->date('date');

            $table->decimal('spend_total', 15, 2)->default(0);
            $table->decimal('refund_total', 15, 2)->default(0);
            $table->decimal('profit', 15, 2)->default(0);
            $table->decimal('points_earned', 15, 2)->default(0);
            $table->decimal('points_used', 15, 2)->default(0);

            $table->unsignedInteger('transactions')->default(0);
            $table->unsignedInteger('refund_count')->default(0);

            $table->timestamps();

            $table->unique(['user_id', 'customer_id', 'date'], 'dcm_user_customer_date_unique');
            $table->index(['customer_id', 'date'], 'dcm_customer_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_customer_metrics');
    }
};
