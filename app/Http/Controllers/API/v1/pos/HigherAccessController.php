<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Pos\HigherAccessRequest;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HigherAccessController extends Controller
{
    use ApiResponse;

    /**
     * Create a new higher access request (from POS)
     * POST /api/v1/auth/higher-access/request
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'user_name' => 'required|string|max:255',
            'store_id' => 'required|integer|exists:stores,id',
            'store_name' => 'required|string|max:255',
            'pos_id' => 'required|integer|exists:pos,id',
            'pos_name' => 'required|string|max:255',
            'permission_type' => ['required', Rule::in(['discounts', 'refunds', 'delete_items', 'cash_out', 'credit_sale', 'locked_unit', 'credit_payment'])],
            'context_data' => 'nullable|array',
            'device_id' => 'required|string|max:255',
        ]);

        // Auto-expire stale requests
        HigherAccessRequest::expired()->update(['status' => 'expired']);

        // Cancel any existing pending request from this user/device
        HigherAccessRequest::where('user_id', $validated['user_id'])
            ->where('device_id', $validated['device_id'])
            ->pending()
            ->update(['status' => 'cancelled']);

        $accessRequest = HigherAccessRequest::create([
            'request_id' => Str::uuid()->toString(),
            'user_id' => $validated['user_id'],
            'user_name' => $validated['user_name'],
            'store_id' => $validated['store_id'],
            'store_name' => $validated['store_name'],
            'pos_id' => $validated['pos_id'],
            'pos_name' => $validated['pos_name'],
            'permission_type' => $validated['permission_type'],
            'context_data' => $validated['context_data'] ?? null,
            'device_id' => $validated['device_id'],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(2),
        ]);

        // Send push notification to users who can approve this permission type
        $this->notifyHigherAccessRequest($accessRequest);

        return $this->success([
            'request_id' => $accessRequest->request_id,
            'expires_at' => $accessRequest->expires_at->toIso8601String(),
        ], 'Request submitted');
    }

    /**
     * Check request status (POS polling)
     * GET /api/v1/auth/higher-access/status/{requestId}
     */
    public function status(string $requestId): JsonResponse
    {
        $request = HigherAccessRequest::where('request_id', $requestId)->first();

        if (! $request) {
            return $this->notFound('Request not found');
        }

        // Auto-expire if needed
        if ($request->status === 'pending' && $request->isExpired()) {
            $request->update(['status' => 'expired']);
        }

        return $this->success([
            'request_id' => $request->request_id,
            'status' => $request->status,
            'approver_id' => $request->approver_id,
            'approver_name' => $request->approver_name,
            'response_message' => $request->response_message,
            'responded_at' => $request->responded_at?->toIso8601String(),
        ]);
    }

    /**
     * Cancel a pending request (from POS)
     * POST /api/v1/auth/higher-access/cancel/{requestId}
     */
    public function cancel(string $requestId): JsonResponse
    {
        $request = HigherAccessRequest::where('request_id', $requestId)
            ->where('status', 'pending')
            ->first();

        if (! $request) {
            return $this->notFound('Request not found or already processed');
        }

        // Verify ownership
        if ($request->user_id !== auth()->id()) {
            return $this->forbidden('You can only cancel your own requests');
        }

        $request->update(['status' => 'cancelled']);

        return $this->success(['status' => 'cancelled'], 'Request cancelled');
    }

    /**
     * Get pending requests for a store (for Dashboard)
     * GET /api/v1/auth/higher-access/pending
     */
    public function pending(Request $request): JsonResponse
    {
        $storeId = $request->query('store_id');

        // First, auto-expire old pending requests
        HigherAccessRequest::expired()->update(['status' => 'expired']);

        $query = HigherAccessRequest::pending();

        if ($storeId) {
            $query->forStore($storeId);
        } else {
            // No store_id: return requests from all stores belonging to the authenticated user's business
            $authUser = auth()->user();
            if ($authUser) {
                $storeIds = \App\Models\Settings\Store::where('user_id', $authUser->user_id ?? $authUser->id)
                    ->pluck('id');
                $query->whereIn('store_id', $storeIds);
            }
        }

        $requests = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($r) => [
                'request_id' => $r->request_id,
                'user_id' => $r->user_id,
                'user_name' => $r->user_name,
                'store_name' => $r->store_name,
                'pos_id' => $r->pos_id,
                'pos_name' => $r->pos_name,
                'permission_type' => $r->permission_type,
                'context_data' => $r->context_data,
                'expires_at' => $r->expires_at->toIso8601String(),
                'remaining_seconds' => max(0, now()->diffInSeconds($r->expires_at, false)),
                'created_at' => $r->created_at->toIso8601String(),
            ]);

        return $this->success(['requests' => $requests]);
    }

    /**
     * Respond to a request (from Dashboard)
     * POST /api/v1/auth/higher-access/respond
     */
    public function respond(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_id' => 'required|uuid',
            'status' => ['required', Rule::in(['approved', 'denied'])],
            'message' => 'nullable|string|max:500',
        ]);

        $accessRequest = HigherAccessRequest::where('request_id', $validated['request_id'])
            ->where('status', 'pending')
            ->first();

        if (! $accessRequest) {
            return $this->notFound('Request not found or already processed');
        }

        if ($accessRequest->isExpired()) {
            $accessRequest->update(['status' => 'expired']);

            return $this->error('Request has expired', 410);
        }

        // Validate approver permission
        $approver = auth()->user();
        if (! $this->canApprove($approver, $accessRequest->permission_type)) {
            return $this->forbidden('You do not have permission to approve this request');
        }

        $accessRequest->update([
            'status' => $validated['status'],
            'approver_id' => $approver->id,
            'approver_name' => $approver->name,
            'responded_at' => now(),
            'response_message' => $validated['message'] ?? null,
        ]);

        return $this->success([
            'request_id' => $accessRequest->request_id,
            'status' => $accessRequest->status,
        ], 'Response recorded');
    }

    private function notifyHigherAccessRequest(HigherAccessRequest $accessRequest): void
    {
        try {
            $employee = User::find($accessRequest->user_id);
            if (! $employee) {
                return;
            }

            $businessUserId = $employee->user_id;
            $permission = match ($accessRequest->permission_type) {
                'discounts' => 'discounts',
                'refunds' => 'rfnd',
                'delete_items' => 'delete_items',
                'cash_out' => 'csh_out',
                'credit_sale' => 'crdt_sale',
                'locked_unit' => 'unit_lock_approve',
                'credit_payment' => 'crdt_pymnt',
                default => null,
            };

            if (! $permission || ! $businessUserId) {
                return;
            }

            $typeLabel = str_replace('_', ' ', $accessRequest->permission_type);

            app(FcmService::class)->sendToUsersWithPermission(
                $businessUserId,
                $permission,
                'Access Request',
                "{$accessRequest->user_name} requests {$typeLabel} access at {$accessRequest->store_name}",
                ['type' => 'higher_access_request', 'id' => $accessRequest->request_id]
            );
        } catch (\Exception $e) {
            Log::warning('FCM notification failed for higher access request: '.$e->getMessage());
        }
    }

    private function canApprove($user, string $permissionType): bool
    {
        $role = $user->role;
        if (! $role) {
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
}
