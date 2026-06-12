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
        Schema::table('purchases', function (Blueprint $table) {
            // Add cached amount_paid column to track total payments
            $table->double('amount_paid', 15, 2)->default(0)->after('total');
        });

        // Note: payment_status already exists in the purchases table
        // We'll use it as: 0=Unpaid, 1=Partial, 2=Fully Paid
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('amount_paid');
        });
    }
};
