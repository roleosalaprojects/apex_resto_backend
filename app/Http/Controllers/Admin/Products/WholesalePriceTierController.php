<?php

namespace App\Http\Controllers\Admin\Products;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Products\Item;
use App\Models\Products\WholesalePriceTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WholesalePriceTierController extends Controller
{
    use ApiResponse;

    public function index(Item $item): JsonResponse
    {
        $tiers = $item->wholesalePriceTiers()->orderBy('min_qty', 'asc')->get();

        return $this->success(['tiers' => $tiers]);
    }

    public function store(Request $request, Item $item): JsonResponse
    {
        $validated = $request->validate([
            'min_qty' => ['required', 'integer', 'min:1'],
            'discount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $exists = $item->wholesalePriceTiers()
            ->where('min_qty', $validated['min_qty'])
            ->exists();

        if ($exists) {
            return $this->error('A tier with this minimum quantity already exists.', 422);
        }

        $tier = $item->wholesalePriceTiers()->create($validated);

        return $this->created($tier, 'Tier created successfully');
    }

    public function update(Request $request, WholesalePriceTier $tier): JsonResponse
    {
        $validated = $request->validate([
            'min_qty' => ['required', 'integer', 'min:1'],
            'discount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $exists = WholesalePriceTier::where('item_id', $tier->item_id)
            ->where('min_qty', $validated['min_qty'])
            ->where('id', '!=', $tier->id)
            ->exists();

        if ($exists) {
            return $this->error('A tier with this minimum quantity already exists.', 422);
        }

        $tier->update($validated);

        return $this->success($tier, 'Tier updated successfully');
    }

    public function destroy(WholesalePriceTier $tier): JsonResponse
    {
        $tier->delete();

        return $this->success(null, 'Tier deleted successfully');
    }
}
