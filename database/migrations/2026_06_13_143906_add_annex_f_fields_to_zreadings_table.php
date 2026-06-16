<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Annex F gap-fill for Z-Readings: void/return document ranges, a
     * per-terminal z_counter distinct from reset_counter, void aggregates,
     * extra tender lines and gross sales. (Accumulated-sales columns are
     * already double(15,2).)
     */
    public function up(): void
    {
        Schema::table('zreadings', function (Blueprint $table) {
            $table->unsignedBigInteger('first_void_no')->nullable()->after('refund_last_or');
            $table->unsignedBigInteger('last_void_no')->nullable()->after('first_void_no');
            $table->unsignedBigInteger('first_return_no')->nullable()->after('last_void_no');
            $table->unsignedBigInteger('last_return_no')->nullable()->after('first_return_no');
            $table->unsignedBigInteger('z_counter')->nullable()->after('last_return_no');
            $table->double('void_amount', 15, 2)->default(0)->after('z_counter');
            $table->unsignedInteger('void_count')->default(0)->after('void_amount');
            $table->double('cheque', 15, 2)->default(0)->after('void_count');
            $table->double('card', 15, 2)->default(0)->after('cheque');
            $table->double('gift_cert', 15, 2)->default(0)->after('card');
            $table->double('bank_transfer', 15, 2)->default(0)->after('gift_cert');
            $table->double('gross_sales', 15, 2)->default(0)->after('bank_transfer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zreadings', function (Blueprint $table) {
            $table->dropColumn(['first_void_no', 'last_void_no', 'first_return_no', 'last_return_no', 'z_counter', 'void_amount', 'void_count', 'cheque', 'card', 'gift_cert', 'bank_transfer', 'gross_sales']);
        });
    }
};
