<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_credit_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('z_reading_id')->nullable()->after('store_id')->index();
            $table->unsignedBigInteger('shift_reading_id')->nullable()->after('z_reading_id')->index();
        });

        // Persist per-method credit payment totals on the readings so reprints
        // and admin reports stay accurate.
        Schema::table('zreadings', function (Blueprint $table) {
            $table->double('credit_payments_cash', 15, 2)->default(0)->after('credit_sales');
            $table->double('credit_payments_ewallet', 15, 2)->default(0)->after('credit_payments_cash');
            $table->double('credit_payments_bank', 15, 2)->default(0)->after('credit_payments_ewallet');
            $table->double('credit_payments_cheque', 15, 2)->default(0)->after('credit_payments_bank');
        });

        Schema::table('shift_readings', function (Blueprint $table) {
            $table->decimal('credit_payments_cash', 12, 2)->default(0)->after('credit_sales');
            $table->decimal('credit_payments_ewallet', 12, 2)->default(0)->after('credit_payments_cash');
            $table->decimal('credit_payments_bank', 12, 2)->default(0)->after('credit_payments_ewallet');
            $table->decimal('credit_payments_cheque', 12, 2)->default(0)->after('credit_payments_bank');
        });
    }

    public function down(): void
    {
        Schema::table('customer_credit_transactions', function (Blueprint $table) {
            $table->dropColumn(['z_reading_id', 'shift_reading_id']);
        });

        Schema::table('zreadings', function (Blueprint $table) {
            $table->dropColumn([
                'credit_payments_cash',
                'credit_payments_ewallet',
                'credit_payments_bank',
                'credit_payments_cheque',
            ]);
        });

        Schema::table('shift_readings', function (Blueprint $table) {
            $table->dropColumn([
                'credit_payments_cash',
                'credit_payments_ewallet',
                'credit_payments_bank',
                'credit_payments_cheque',
            ]);
        });
    }
};
