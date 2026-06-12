<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Settings\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $stores = Store::where('status', 1)->get();

        return $this->success(['stores' => $stores]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->success(null);
    }

    public function show(Store $store): JsonResponse
    {
        return $this->success($store);
    }

    public function update(Request $request, Store $store): JsonResponse
    {
        return $this->success(null);
    }

    public function destroy(Store $store): JsonResponse
    {
        return $this->success(null);
    }
}
