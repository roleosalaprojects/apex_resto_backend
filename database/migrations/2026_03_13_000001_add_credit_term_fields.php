<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('credit_term_days')->default(30)->after('credit_balance');
        });

        Schema::table('customer_credit_transactions', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('balance_after');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('credit_term_days');
        });

        Schema::table('customer_credit_transactions', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
