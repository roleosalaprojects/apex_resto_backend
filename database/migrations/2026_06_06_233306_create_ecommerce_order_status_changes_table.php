<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per status transition on an EcommerceOrder. The order's
 * current status lives on ecommerce_orders.status; this table is the
 * audit trail behind it — when each transition happened, who caused
 * it, and any context note.
 *
 * NULL from_status marks the order-creation event (no prior state).
 * NULL changed_by means the change wasn't made by a logged-in admin
 * (typically the customer placing the order via /shop checkout).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_order_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_order_id')
                ->constrained('ecommerce_orders')
                ->cascadeOnDelete();
            $table->tinyInteger('from_status')->nullable();
            $table->tinyInteger('to_status');
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Explicit short index names — the auto-generated
            // `ecommerce_order_status_changes_<columns>_index` blows past
            // MySQL's 64-char identifier limit.
            $table->index(['ecommerce_order_id', 'created_at'], 'eosc_order_created_idx');
            $table->index('to_status', 'eosc_to_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_order_status_changes');
    }
};
