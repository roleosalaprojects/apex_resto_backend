<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Z-Reading aggregate fields to shift_readings so each shift
     * stores its own VAT breakdown, discount summary, and VAT adjustments.
     */
    public function up(): void
    {
        Schema::table('shift_readings', function (Blueprint $table) {
            // Transactions & Invoice Range
            $table->unsignedInteger('transactions')->default(0)->after('refunds');
            $table->string('first_or')->nullable()->after('transactions');
            $table->string('last_or')->nullable()->after('first_or');
            $table->string('refund_first_or')->nullable()->after('last_or');
            $table->string('refund_last_or')->nullable()->after('refund_first_or');

            // VAT Breakdown
            $table->decimal('vatable', 12, 2)->default(0)->after('refund_last_or');
            $table->decimal('vat', 12, 2)->default(0)->after('vatable');
            $table->decimal('vat_exempt', 12, 2)->default(0)->after('vat');
            $table->decimal('zero_rated', 12, 2)->default(0)->after('vat_exempt');

            // Discount Summary
            $table->decimal('reg_discount', 12, 2)->default(0)->after('zero_rated');
            $table->decimal('sc_discount', 12, 2)->default(0)->after('reg_discount');
            $table->decimal('pwd_discount', 12, 2)->default(0)->after('sc_discount');
            $table->decimal('solo_parent_discount', 12, 2)->default(0)->after('pwd_discount');
            $table->decimal('naac_discount', 12, 2)->default(0)->after('solo_parent_discount');
            $table->decimal('vat_special_discounts', 12, 2)->default(0)->after('naac_discount');

            // VAT Adjustment
            $table->decimal('sc_vat_adjustment', 12, 2)->default(0)->after('vat_special_discounts');
            $table->decimal('pwd_vat_adjustment', 12, 2)->default(0)->after('sc_vat_adjustment');
            $table->decimal('sp_vat_adjustment', 12, 2)->default(0)->after('pwd_vat_adjustment');
            $table->decimal('naac_vat_adjustment', 12, 2)->default(0)->after('sp_vat_adjustment');
            $table->decimal('vat_on_refunds', 12, 2)->default(0)->after('naac_vat_adjustment');

            // Transaction Counts
            $table->unsignedInteger('sc_transactions')->default(0)->after('vat_on_refunds');
            $table->unsignedInteger('pwd_transactions')->default(0)->after('sc_transactions');
            $table->unsignedInteger('sp_transactions')->default(0)->after('pwd_transactions');
            $table->unsignedInteger('naac_transactions')->default(0)->after('sp_transactions');
            $table->unsignedInteger('reg_disc_transactions')->default(0)->after('naac_transactions');
        });
    }

    public function down(): void
    {
        Schema::table('shift_readings', function (Blueprint $table) {
            $table->dropColumn([
                'transactions', 'first_or', 'last_or', 'refund_first_or', 'refund_last_or',
                'vatable', 'vat', 'vat_exempt', 'zero_rated',
                'reg_discount', 'sc_discount', 'pwd_discount', 'solo_parent_discount',
                'naac_discount', 'vat_special_discounts',
                'sc_vat_adjustment', 'pwd_vat_adjustment', 'sp_vat_adjustment',
                'naac_vat_adjustment', 'vat_on_refunds',
                'sc_transactions', 'pwd_transactions', 'sp_transactions',
                'naac_transactions', 'reg_disc_transactions',
            ]);
        });
    }
};
