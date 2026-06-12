<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\VoucherResource;
use App\Http\Traits\ApiResponse;
use App\Models\Pos\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VoucherController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Voucher::with('store:id,name');

        // Search by code or name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%");
            });
        }

        // Filter by store
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            match ($request->status) {
                'active' => $query->where('is_active', true)
                    ->where('expires_at', '>', now())
                    ->whereColumn('used_count', '<', 'max_uses'),
                'inactive' => $query->where('is_active', false),
                'expired' => $query->where('expires_at', '<=', now()),
                'used_up' => $query->whereColumn('used_count', '>=', 'max_uses'),
                default => null,
            };
        }

        $query->orderByDesc('created_at');

        $perPage = $request->get('per_page', 15);
        $vouchers = $query->paginate($perPage);

        return $this->success([
            'vouchers' => VoucherResource::collection($vouchers),
            'pagination' => [
                'current_page' => $vouchers->currentPage(),
                'last_page' => $vouchers->lastPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
            ],
        ]);
    }

    public function show(Voucher $voucher): JsonResponse
    {
        $voucher->load('store:id,name');

        // Usage stats
        $usages = $voucher->usages();
        $stats = [
            'total_uses' => $voucher->used_count,
            'total_discount_applied' => (float) $usages->sum('amount_applied'),
            'unique_stores' => $usages->distinct('store_id')->count('store_id'),
            'unique_users' => $usages->distinct('user_id')->count('user_id'),
        ];

        // Recent usages
        $recentUsages = $voucher->usages()
            ->with(['user:id,name', 'store:id,name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($usage) => [
                'id' => $usage->id,
                'amount_applied' => $usage->amount_applied,
                'user' => $usage->user ? ['id' => $usage->user->id, 'name' => $usage->user->name] : null,
                'store' => $usage->store ? ['id' => $usage->store->id, 'name' => $usage->store->name] : null,
                'created_at' => $usage->created_at?->toIso8601String(),
            ]);

        return $this->success([
            'voucher' => new VoucherResource($voucher),
            'stats' => $stats,
            'recent_usages' => $recentUsages,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:vouchers,code',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'minimum_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'required|integer|min:1',
            'store_id' => 'nullable|exists:stores,id',
            'expires_at' => 'required|date|after:now',
            'is_active' => 'nullable|boolean',
        ]);

        // Generate code if not provided
        if (empty($validated['code'])) {
            $validated['code'] = $this->generateUniqueCode();
        } else {
            $validated['code'] = strtoupper($validated['code']);
        }

        $validated['minimum_amount'] = $validated['minimum_amount'] ?? 0;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $voucher = Voucher::create($validated);
        $voucher->load('store:id,name');

        return $this->created([
            'voucher' => new VoucherResource($voucher),
        ], 'Voucher created successfully.');
    }

    public function update(Request $request, Voucher $voucher): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:vouchers,code,'.$voucher->id,
            'name' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0.01',
            'minimum_amount' => 'sometimes|numeric|min:0',
            'max_uses' => 'sometimes|integer|min:1',
            'store_id' => 'nullable|exists:stores,id',
            'expires_at' => 'sometimes|date',
            'is_active' => 'sometimes|boolean',
        ]);

        // Guard: max_uses cannot go below used_count
        if (isset($validated['max_uses']) && $validated['max_uses'] < $voucher->used_count) {
            return $this->error(
                'Max uses cannot be less than current used count ('.$voucher->used_count.').',
                422
            );
        }

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $voucher->update($validated);
        $voucher->load('store:id,name');

        return $this->success([
            'voucher' => new VoucherResource($voucher),
        ], 'Voucher updated successfully.');
    }

    public function destroy(Voucher $voucher): JsonResponse
    {
        // Guard: refuse if voucher has been used
        if ($voucher->used_count > 0) {
            return $this->error(
                'Cannot delete a voucher that has been used ('.$voucher->used_count.' uses). Deactivate it instead.',
                422
            );
        }

        $voucher->delete();

        return $this->success(null, 'Voucher deleted successfully.');
    }

    public function generateCode(): JsonResponse
    {
        return $this->success([
            'code' => $this->generateUniqueCode(),
        ]);
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }
}
