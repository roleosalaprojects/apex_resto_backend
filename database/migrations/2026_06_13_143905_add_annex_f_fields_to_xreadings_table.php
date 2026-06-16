<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Annex F gap-fill for X-Readings: per-tender breakdown and
     * void/return aggregates. beginning_or/ending_or/opening_fund/cash/
     * e_wallet already exist on the table.
     */
    public function up(): void
    {
        Schema::table('xreadings', function (Blueprint $table) {
            $table->double('cheque', 15, 2)->default(0)->after('e_wallet');
            $table->double('card', 15, 2)->default(0)->after('cheque');
            $table->double('gift_cert', 15, 2)->default(0)->after('card');
            $table->double('bank_transfer', 15, 2)->default(0)->after('gift_cert');
            $table->double('credit', 15, 2)->default(0)->after('bank_transfer');
            $table->double('void_amount', 15, 2)->default(0)->after('credit');
            $table->unsignedInteger('void_count')->default(0)->after('void_amount');
            $table->double('return_amount', 15, 2)->default(0)->after('void_count');
            $table->string('cashier_name')->nullable()->after('return_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xreadings', function (Blueprint $table) {
            $table->dropColumn(['cheque', 'card', 'gift_cert', 'bank_transfer', 'credit', 'void_amount', 'void_count', 'return_amount', 'cashier_name']);
        });
    }
};
