<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employees\Role;
use App\Models\Pos\HigherAccessRequest;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Web-side approval surface for HigherAccessRequest rows.
 *
 * Mirrors the dashboard app's approval list: managers/owners logged into the
 * admin web can see pending POS requests in real-time (3s polling) and
 * approve/deny them without picking up a phone.
 *
 * Authorization model: a user sees a request iff their role's matching flag
 * is set for that request's permission_type — same canApprove() rule the
 * POS API controller uses on the dashboard side. No new RBAC introduced.
 */
class AccessRequestController extends Controller
{
    /**
     * GET /admin/access-requests/pending — JSON for the front-end poller.
     */
    public function pending(Request $request): JsonResponse
    {
        // Sweep expired rows so the badge doesn't claim stale work.
        HigherAccessRequest::expired()->update(['status' => 'expired']);

        $approver = auth()->user();
        $approvable = $this->approvableTypesFor($approver->role);

        $tenantStoreIds = Store::query()
            ->where('user_id', $approver->user_id ?? $approver->id)
            ->pluck('id');

        $rows = HigherAccessRequest::query()
            ->pending()
            ->whereIn('store_id', $tenantStoreIds)
            ->whereIn('permission_type', $approvable)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (HigherAccessRequest $r) => [
                'request_id' => $r->request_id,
                'user_name' => $r->user_name,
                'store_name' => $r->store_name,
                'pos_name' => $r->pos_name,
                'permission_type' => $r->permission_type,
                'permission_label' => $this->permissionLabel($r->permission_type),
                'context_data' => $r->context_data,
                'expires_at' => $r->expires_at?->toIso8601String(),
                'remaining_seconds' => max(0, (int) now()->diffInSeconds($r->expires_at, false)),
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $rows->count(),
                'requests' => $rows->values(),
            ],
        ]);
    }

    /**
     * POST /admin/access-requests/{requestId}/respond — approve or deny.
     */
    public function respond(Request $request, string $requestId): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'denied'])],
            'message' => 'nullable|string|max:500',
        ]);

        $accessRequest = HigherAccessRequest::query()
            ->where('request_id', $requestId)
            ->where('status', 'pending')
            ->first();

        if ($accessRequest === null) {
            return response()->json(['success' => false, 'message' => 'Request not found or already processed.'], 404);
        }

        if ($accessRequest->isExpired()) {
            $accessRequest->update(['status' => 'expired']);

            return response()->json(['success' => false, 'message' => 'Request has expired.'], 410);
        }

        $approver = auth()->user();
        if (! $this->canApprove($approver->role, $accessRequest->permission_type)) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to approve this request.'], 403);
        }

        // Tenant guard: don't let a logged-in admin from another business respond
        // to requests outside their store set, even if they technically have the flag.
        $tenantStoreIds = Store::query()
            ->where('user_id', $approver->user_id ?? $approver->id)
            ->pluck('id')
            ->all();
        if (! in_array($accessRequest->store_id, $tenantStoreIds, true)) {
            return response()->json(['success' => false, 'message' => 'Request belongs to another tenant.'], 403);
        }

        $accessRequest->update([
            'status' => $validated['status'],
            'approver_id' => $approver->id,
            'approver_name' => $approver->name,
            'responded_at' => now(),
            'response_message' => $validated['message'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Response recorded.',
            'data' => [
                'request_id' => $accessRequest->request_id,
                'status' => $accessRequest->status,
            ],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function approvableTypesFor(?Role $role): array
    {
        if ($role === null) {
            return [];
        }

        return collect([
            'discounts' => $role->discounts,
            'refunds' => $role->rfnd,
            'delete_items' => $role->delete_items,
            'cash_out' => $role->csh_out,
            'credit_sale' => $role->crdt_sale,
            'locked_unit' => $role->unit_lock_approve,
            'credit_payment' => $role->crdt_pymnt,
        ])->filter()->keys()->values()->all();
    }

    private function canApprove(?Role $role, string $permissionType): bool
    {
        if ($role === null) {
            return false;
        }

        return match ($permissionType) {
            'discounts' => (bool) $role->discounts,
            'refunds' => (bool) $role->rfnd,
            'delete_items' => (bool) $role->delete_items,
            'cash_out' => (bool) $role->csh_out,
            'credit_sale' => (bool) $role->crdt_sale,
            'locked_unit' => (bool) $role->unit_lock_approve,
            'credit_payment' => (bool) $role->crdt_pymnt,
            default => false,
        };
    }

    private function permissionLabel(string $type): string
    {
        return match ($type) {
            'discounts' => 'Apply Discount',
            'refunds' => 'Process Refund',
            'delete_items' => 'Delete Item',
            'cash_out' => 'Void Cash Out',
            'credit_sale' => 'Credit Sale',
            'locked_unit' => 'Use Locked Unit',
            'credit_payment' => 'Receive Credit Payment',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }
}
