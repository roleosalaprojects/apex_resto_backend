<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_credit_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_id')->nullable()->after('payment_method');
            $table->string('reference_number', 100)->nullable()->after('bank_id');

            $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_credit_transactions', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropColumn(['bank_id', 'reference_number']);
        });
    }
};
