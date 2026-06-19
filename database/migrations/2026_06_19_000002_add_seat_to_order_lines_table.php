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
            // Diner/seat number a line belongs to, for bill-by-seat. NULL =
            // unassigned (shared / table-level). Settling groups lines by seat.
            $table->unsignedInteger('seat')->nullable()->index()->after('sales_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropColumn('seat');
        });
    }
};
