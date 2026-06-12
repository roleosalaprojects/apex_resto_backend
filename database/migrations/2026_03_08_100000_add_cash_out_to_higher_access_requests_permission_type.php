<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE higher_access_requests MODIFY COLUMN permission_type ENUM('discounts', 'refunds', 'delete_items', 'cash_out')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE higher_access_requests MODIFY COLUMN permission_type ENUM('discounts', 'refunds', 'delete_items')");
    }
};
