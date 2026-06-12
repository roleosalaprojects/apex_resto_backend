<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos staff capture when the customer picks up an order — receipt
 * signing, customer holding the goods, handover confirmation. Optional
 * but recommended for any contested pickup ("the customer claims they
 * didn't get item X" → check the photo).
 *
 * Lives on the order rather than the sale because pickup is an
 * order-lifecycle event (status PREPARING → PICKED_UP), not a payment
 * event. Mirrors the structure of sale_payment_proofs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_order_pickup_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_order_id')
                ->constrained('ecommerce_orders')
                ->cascadeOnDelete();
            $table->string('path');
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            // Short explicit index name to dodge the 64-char ceiling.
            $table->index('ecommerce_order_id', 'eopp_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_order_pickup_proofs');
    }
};
