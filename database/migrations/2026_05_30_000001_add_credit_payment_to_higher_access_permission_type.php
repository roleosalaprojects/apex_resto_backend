<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Extend the permission_type enum to include 'credit_payment' so the
     * POS can submit higher-access requests for cashiers without
     * roles.crdt_pymnt who need a manager to authorize a credit payment.
     * Approval reuses crdt_pymnt (Option A from the spec).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE higher_access_requests MODIFY COLUMN permission_type ENUM('discounts', 'refunds', 'delete_items', 'cash_out', 'credit_sale', 'locked_unit', 'credit_payment')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE higher_access_requests MODIFY COLUMN permission_type ENUM('discounts', 'refunds', 'delete_items', 'cash_out', 'credit_sale', 'locked_unit')");
    }
};
