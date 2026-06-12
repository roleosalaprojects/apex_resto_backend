<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreRequest;
use App\Http\Requests\Store\UpdateRequest;
use App\Http\Resources\StoreResource;
use App\Http\Traits\ApiResponse;
use App\Models\Settings\Store;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $stores = Store::where('status', true)->get();

        return $this->success(StoreResource::collection($stores));
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $store = Store::create([
            'name' => $validated['name'],
            'status' => true,
            'header' => $validated['header'],
            'footer' => $validated['footer'],
            'tin' => $validated['tin'],
            'vat_reg' => $validated['vat_reg'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'counter' => 1,
            'user_id' => auth()->user()->user_id,
        ]);

        return $this->created(
            new StoreResource($store),
            $store->name.' store created.'
        );
    }

    public function show(Store $store): JsonResponse
    {
        return $this->success(new StoreResource($store));
    }

    public function update(UpdateRequest $request, Store $store): JsonResponse
    {
        $validated = $request->validated();
        $store->update([
            'name' => $validated['name'],
            'header' => $validated['header'],
            'footer' => $validated['footer'],
            'tin' => $validated['tin'],
            'vat_reg' => $validated['vat_reg'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
        ]);

        return $this->success(
            new StoreResource($store),
            $store->name.' store updated.'
        );
    }

    public function destroy(Store $store): JsonResponse
    {
        if (! $store->status) {
            return $this->forbidden($store->name.' store has already been deleted.');
        }

        $store->update([
            'status' => false,
        ]);

        return $this->success(null, $store->name.' store successfully deleted.');
    }
}
