<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Extend the permission_type enum to include the values the controller
     * already validates against: 'credit_sale' (existing latent gap) and
     * 'locked_unit' (new feature).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE higher_access_requests MODIFY COLUMN permission_type ENUM('discounts', 'refunds', 'delete_items', 'cash_out', 'credit_sale', 'locked_unit')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE higher_access_requests MODIFY COLUMN permission_type ENUM('discounts', 'refunds', 'delete_items', 'cash_out')");
    }
};
