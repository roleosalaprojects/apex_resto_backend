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
        Schema::table('order_lines', function (Blueprint $table) {
            // Links a line to the Sale that settled it. NULL = unsettled.
            // A single order can produce multiple Sales (split bill), so this
            // lives per-line rather than only on the order header.
            $table->unsignedBigInteger('sales_id')->nullable()->index()->after('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropColumn('sales_id');
        });
    }
};
