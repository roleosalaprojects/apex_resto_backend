<?php

namespace App\Observers;

use App\Models\InventoryManagement\Purchase;
use App\Models\InventoryManagement\PurchaseApproval;

/**
 * §1.5 of development/specs/purchase_order_audit_and_remediation.md.
 *
 * The schema carries the approval status in two places that drift:
 *
 *   - `Purchase.approval_status`        integer (0/1/2/3 enum)
 *   - `PurchaseApproval.status`         string ('pending'/'approved'/'rejected')
 *
 * Pre-fix nothing tied them together. A controller could write one
 * and forget the other; a raw SQL update or seed could leave them
 * inconsistent.
 *
 * Fix: when a PurchaseApproval row is created or updated, sync the
 * parent Purchase's int status to match. One-way invariant — parent
 * still has to be written explicitly when a status flip skips writing
 * an approval row (rare, but possible for admin SQL fixups).
 */
class PurchaseApprovalObserver
{
    /** Map the child's string status to the parent's int constant. */
    private const STATUS_MAP = [
        'pending' => Purchase::APPROVAL_PENDING,
        'approved' => Purchase::APPROVAL_APPROVED,
        'rejected' => Purchase::APPROVAL_REJECTED,
    ];

    public function created(PurchaseApproval $purchaseApproval): void
    {
        $this->syncParent($purchaseApproval);
    }

    public function updated(PurchaseApproval $purchaseApproval): void
    {
        $this->syncParent($purchaseApproval);
    }

    private function syncParent(PurchaseApproval $approval): void
    {
        if (! isset(self::STATUS_MAP[$approval->status])) {
            return;
        }

        $parent = Purchase::find($approval->purchase_id);
        if (! $parent) {
            return;
        }

        $targetStatus = self::STATUS_MAP[$approval->status];
        if ((int) $parent->approval_status === $targetStatus) {
            return; // already in sync — avoid an unnecessary write
        }

        $parent->forceFill(['approval_status' => $targetStatus])->save();
    }
}
