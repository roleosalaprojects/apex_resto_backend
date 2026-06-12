<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL UNIQUE allows multiple NULLs, so customers without a
        // phone on file (legacy / POS-counter imports) still slot in
        // fine. The constraint is the backstop: even if two parallel
        // register requests race past the FormRequest unique check,
        // one insert fails with SQLSTATE 23000 instead of producing
        // two customer rows for the same phone.
        Schema::table('customers', function (Blueprint $table) {
            $table->unique('phone', 'customers_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_phone_unique');
        });
    }
};
