<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreRequest;
use App\Http\Requests\Supplier\UpdateRequest;
use App\Http\Traits\ApiResponse;
use App\Models\InventoryManagement\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::where('status', 1)
            ->when($request->term, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%'.$request->term.'%')
                        ->orWhere('contact', 'like', '%'.$request->term.'%')
                        ->orWhere('email', 'like', '%'.$request->term.'%')
                        ->orWhere('city', 'like', '%'.$request->term.'%');
                });
            })
            ->orderBy('name')
            ->get();

        return $this->success(['suppliers' => $suppliers]);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['status'] = true;
        $validated['user_id'] = auth()->user()->user_id;

        $supplier = Supplier::create($validated);

        return $this->created([
            'supplier' => $supplier,
        ], 'Supplier created successfully!');
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return $this->success(['supplier' => $supplier]);
    }

    public function update(UpdateRequest $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validated();
        $supplier->update($validated);

        return $this->success([
            'supplier' => $supplier->fresh(),
        ], 'Supplier updated successfully!');
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->update(['status' => false]);

        return $this->success(null, 'Supplier deleted successfully!');
    }
}
