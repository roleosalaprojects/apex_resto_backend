<?php

use App\Models\Ecommerce\EcommerceOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Before the lifecycle extension that added STATUS_PAID (3), the cashless
 * record-payment flow only advanced the order to STATUS_VERIFIED (1), so
 * a "paid" order was implicit (had a Sale row) but its status column
 * still read "verified". This backfill walks every order with a linked
 * Sale and bumps its status to PAID if it's still sitting at 0 or 1.
 *
 * Cancelled (2), preparing (4), and picked_up (5) are intentionally left
 * alone — those are downstream states the new code reaches via explicit
 * admin action, and we shouldn't roll any of them backward to "paid".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            EcommerceOrder::query()
                ->whereIn('status', [
                    EcommerceOrder::STATUS_PENDING,
                    EcommerceOrder::STATUS_VERIFIED,
                ])
                ->whereHas('sale')
                ->update([
                    'status' => EcommerceOrder::STATUS_PAID,
                ]);
        });
    }

    public function down(): void
    {
        // No reliable inverse — once the status was bumped to PAID we
        // lose whether the original was 0 or 1. Down is a no-op.
    }
};
