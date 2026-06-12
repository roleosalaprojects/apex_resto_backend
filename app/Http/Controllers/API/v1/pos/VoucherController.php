<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Pos\Voucher;
use App\Models\Pos\VoucherUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoucherController extends Controller
{
    use ApiResponse;

    /**
     * Validate a voucher code
     * POST /api/v1/vouchers/check
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'store_id' => 'required|integer|exists:stores,id',
            'cart_total' => 'required|numeric|min:0',
        ]);

        $voucher = Voucher::where('code', strtoupper($validated['code']))->first();

        if (! $voucher) {
            return $this->error('Voucher code not found', 404);
        }

        if (! $voucher->is_active) {
            return $this->error('Voucher is not active', 400);
        }

        if ($voucher->isExpired()) {
            return $this->error('Voucher has expired', 400);
        }

        if (! $voucher->hasUsesRemaining()) {
            return $this->error('Voucher has reached maximum uses', 400);
        }

        if ($voucher->store_id && $voucher->store_id !== (int) $validated['store_id']) {
            return $this->error('Voucher is not valid for this store', 400);
        }

        if (! $voucher->canApplyToAmount($validated['cart_total'])) {
            return $this->error(
                "Minimum cart total of {$voucher->minimum_amount} required to use this voucher",
                400
            );
        }

        return $this->success([
            'voucher' => [
                'id' => $voucher->id,
                'code' => $voucher->code,
                'name' => $voucher->name,
                'amount' => (float) $voucher->amount,
                'minimum_amount' => (float) $voucher->minimum_amount,
                'remaining_uses' => $voucher->remaining_uses,
                'expires_at' => $voucher->expires_at->toIso8601String(),
            ],
        ], 'Voucher is valid');
    }

    /**
     * Apply a voucher (increment usage after sale)
     * POST /api/v1/vouchers/apply
     */
    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'voucher_id' => 'required|integer|exists:vouchers,id',
            'sale_id' => 'required|integer|exists:sales,id',
            'store_id' => 'required|integer|exists:stores,id',
            'pos_id' => 'required|integer|exists:pos,id',
            'amount_applied' => 'required|numeric|min:0',
        ]);

        $voucher = Voucher::find($validated['voucher_id']);

        if (! $voucher) {
            return $this->error('Voucher not found', 404);
        }

        if (! $voucher->hasUsesRemaining()) {
            return $this->error('Voucher has reached maximum uses', 400);
        }

        try {
            DB::transaction(function () use ($voucher, $validated) {
                // Increment voucher usage
                $voucher->incrementUsage();

                // Record usage
                VoucherUsage::create([
                    'voucher_id' => $voucher->id,
                    'sale_id' => $validated['sale_id'],
                    'user_id' => auth()->id(),
                    'store_id' => $validated['store_id'],
                    'pos_id' => $validated['pos_id'],
                    'amount_applied' => $validated['amount_applied'],
                ]);
            });

            return $this->success([
                'voucher_id' => $voucher->id,
                'used_count' => $voucher->fresh()->used_count,
                'remaining_uses' => $voucher->fresh()->remaining_uses,
            ], 'Voucher applied successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to apply voucher: '.$e->getMessage(), 500);
        }
    }
}
