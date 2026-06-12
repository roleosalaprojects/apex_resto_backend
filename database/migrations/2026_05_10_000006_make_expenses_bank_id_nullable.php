<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make expenses.bank_id nullable so payroll accruals and other
     * accounting-only entries can be recorded without forcing a bank
     * withdrawal. The existing FK to banks.id is preserved (it tolerates
     * NULL values fine).
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_id')->nullable(false)->change();
        });
    }
};
