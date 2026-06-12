<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Products\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success(null);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->success(null);
    }

    public function show(Unit $unit): JsonResponse
    {
        return $this->success($unit);
    }

    public function update(Request $request, Unit $unit): JsonResponse
    {
        return $this->success(null);
    }

    public function destroy(Unit $unit): JsonResponse
    {
        return $this->success(null);
    }

    public function getUnits(Request $request): JsonResponse
    {
        $units = Unit::all();

        return $this->success(['units' => $units]);
    }
}
