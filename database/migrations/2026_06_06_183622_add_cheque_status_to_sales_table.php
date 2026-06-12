<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cheques are "money on paper" until they clear the drawee bank, so a
 * cheque sale gets recorded immediately but its bank impact is held
 * back. cheque_status is set only on payment_type = 5 sales:
 *
 *   pending  — cheque written, not yet cleared (initial state)
 *   cleared  — drawee paid; only now does a BankTransaction get written
 *              and the bank balance increase
 *   bounced  — drawee refused; no BankTransaction, customer is charged
 *              back via customer_credit_transactions
 *
 * Stays NULL for all other payment types (cash, e-wallet, bank
 * transfer, credit) — those don't need a clearing state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('cheque_status', 16)
                ->nullable()
                ->after('payment_type');

            $table->index('cheque_status');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['cheque_status']);
            $table->dropColumn('cheque_status');
        });
    }
};
