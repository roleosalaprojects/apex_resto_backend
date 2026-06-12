<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer-signalled payment intent captured at /shop checkout. NOT
 * money received — just "how do you intend to pay?". Drives the
 * pre-selection on the admin/dashboard Record Payment surface and the
 * cashier's expectations at the POS counter.
 *
 * NULL means the customer didn't pick a method (legacy orders, or new
 * orders before the picker is shown), or the picker was explicitly
 * "decide at pickup".
 *
 * Stored values mirror the slugs the Record Payment surfaces already
 * understand:
 *   cash_on_pickup  → Sale::PAYMENT_CASH (1)
 *   gcash           → Sale::PAYMENT_EWALLET (2)
 *   bank_transfer   → Sale::PAYMENT_BANK_TRANSFER (4)
 *   cheque          → Sale::PAYMENT_CHEQUE (5)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $table->string('payment_intent', 20)
                ->nullable()
                ->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $table->dropColumn('payment_intent');
        });
    }
};
