<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\User;
use App\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EcommerceOrderController extends Controller
{
    use ApiResponse;

    /**
     * List verified ecommerce orders.
     *
     * Supports filtering by date range (startDate/endDate on verified_at) and
     * a single smart `term`:
     *   - if `term` parses as a number -> LIKE on total
     *   - otherwise -> LIKE on reference OR customer.name
     *
     * Always paginates at 20 per page so the POS can lazy-load on scroll.
     */
    public function index(Request $request): JsonResponse
    {
        // POS-visible queue. The cashier sees the full lifecycle so they
        // can nudge admin on stuck pending orders and know when something
        // was cancelled. Tap actions are still gated to Verified only
        // (see `cartData` / page-side `_tappable`); the other states are
        // informational.
        $query = EcommerceOrder::whereIn('status', [0, 1, 2, 3, 4, 5])
            ->with(['customer:id,name,code,phone', 'sale:id,ecommerce_order_id,son,created_at']);

        // Filter on created_at (not verified_at) so pending orders
        // — which have verified_at = NULL — are still included when a
        // date range is set.
        if ($request->filled('startDate')) {
            $query->where(
                'created_at',
                '>=',
                Carbon::parse($request->startDate)->startOfDay(),
            );
        }
        if ($request->filled('endDate')) {
            $query->where(
                'created_at',
                '<=',
                Carbon::parse($request->endDate)->endOfDay(),
            );
        }

        if ($request->filled('term')) {
            $term = trim($request->input('term'));
            $like = "%{$term}%";
            $isNumeric = is_numeric($term);

            $query->where(function ($q) use ($like, $isNumeric) {
                $q->where('reference', 'like', $like)
                    ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', $like));
                if ($isNumeric) {
                    $q->orWhere('total', 'like', $like);
                }
            });
        }

        // UI status filter (lifecycle-aligned):
        //   'pending'    → status = 0 (awaiting admin verification)
        //   'ready'      → status = 1 (verified, awaiting cashier ring-up)
        //   'cancelled'  → status = 2 (terminal — informational)
        //   'paid'       → status = 3 (cashless payment recorded)
        //   'preparing'  → status = 4 (packing in progress)
        //   'picked_up'  → status = 5 (collected — historical)
        $statusFilter = $request->input('status');
        $statusMap = [
            'pending' => 0,
            'ready' => 1,
            'cancelled' => 2,
            'paid' => 3,
            'preparing' => 4,
            'picked_up' => 5,
        ];
        if (isset($statusMap[$statusFilter])) {
            $query->where('status', $statusMap[$statusFilter]);
        }

        return $this->success(
            $query->orderByDesc('created_at')->paginate(20),
        );
    }

    /**
     * Show a single ecommerce order with lines.
     */
    public function show(EcommerceOrder $ecommerceOrder): JsonResponse
    {
        $ecommerceOrder->load(['customer:id,name,code,phone', 'lines.item:id,barcode,name']);

        return $this->success($ecommerceOrder);
    }

    /**
     * Return order with full item data for POS cart building.
     */
    public function cartData(EcommerceOrder $ecommerceOrder): JsonResponse
    {
        if (! $ecommerceOrder->isVerified()) {
            return $this->error('Only verified orders can be loaded to cart.', 422);
        }

        if ($ecommerceOrder->sale) {
            return $this->error('This order has already been fulfilled.', 422);
        }

        $ecommerceOrder->load(['customer:id,name,code,phone', 'lines.item']);

        return $this->success($ecommerceOrder);
    }

    /**
     * "Ping" — cashier asks an admin to bump a Pending order's status.
     * Fires an FCM notification to staff who have the verify permission
     * (`sls` covers general staff). Throttled to one ping per order per
     * 30 seconds so the cashier doesn't spam the admin device.
     */
    public function pingAdmin(EcommerceOrder $ecommerceOrder): JsonResponse
    {
        if ($ecommerceOrder->status !== 0) {
            return $this->error(
                'Only pending orders can be pinged.',
                422,
            );
        }

        $throttleKey = "ecommerce_order_ping:{$ecommerceOrder->id}";
        if (Cache::has($throttleKey)) {
            return $this->error(
                'Admin was already pinged for this order recently. Try again in a few seconds.',
                429,
            );
        }
        Cache::put($throttleKey, true, now()->addSeconds(30));

        try {
            $cashier = Auth::guard('api')->user();
            $cashierName = $cashier?->name ?? 'Cashier';
            $businessUserId = $cashier?->user_id;
            if (! $businessUserId) {
                // Fall back to the customer's owning user if we can find it
                // — better than silently doing nothing.
                $businessUserId = User::query()
                    ->where('id', $cashier?->id)
                    ->value('user_id');
            }

            if ($businessUserId) {
                app(FcmService::class)->sendToUsersWithPermission(
                    $businessUserId,
                    'sls',
                    'Verification Requested',
                    "{$cashierName} is asking to verify order {$ecommerceOrder->reference}",
                    [
                        'type' => 'ecommerce_order_ping',
                        'id' => (string) $ecommerceOrder->id,
                        'reference' => $ecommerceOrder->reference,
                    ],
                );
            }
        } catch (\Throwable $e) {
            Log::warning(
                'FCM notification failed for ecommerce order ping: '.$e->getMessage(),
            );
            // Don't 500 — the cashier sees "ping sent" feedback regardless,
            // and the throttle key prevents a tight retry loop.
        }

        return $this->success([
            'reference' => $ecommerceOrder->reference,
            'pinged_at' => now()->toIso8601String(),
        ], 'Admin has been notified.');
    }
}
