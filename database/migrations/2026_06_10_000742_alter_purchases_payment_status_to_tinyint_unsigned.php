<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * §1.2 of development/specs/purchase_order_audit_and_remediation.md
 *
 * The legacy `payment_status` column was created as `tinyint(1)` —
 * MySQL's boolean alias — but newer code paths (recordPayment + the
 * PurchasePayment ledger) write the 0/1/2 enum (UNPAID/PARTIAL/PAID).
 * Three writers, two interpretations of the same column. Reports lie.
 *
 * Fix:
 *   1. Backfill NULL rows to UNPAID (0). Pre-2026 rows with no payment
 *      activity were left null; they're semantically unpaid.
 *   2. Tighten the column to `unsignedTinyInteger` so the enum can
 *      hold its full 0..2 range cleanly, with default 0 and NOT NULL
 *      so future inserts can't drift back into the ambiguous state.
 *
 * No semantic change for existing 0 / 2 rows — those values are
 * already valid in the new column shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchases')->whereNull('payment_status')->update(['payment_status' => 0]);

        Schema::table('purchases', function (Blueprint $table) {
            $table->unsignedTinyInteger('payment_status')->default(0)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // Going back to tinyint(1) silently truncates 2 → 1; that's
        // a known data-loss step but the only sane rollback shape.
        Schema::table('purchases', function (Blueprint $table) {
            $table->boolean('payment_status')->nullable()->default(null)->change();
        });
    }
};
