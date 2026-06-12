<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\StoreRequest;
use App\Http\Requests\Unit\UpdateRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Products\ItemUnit;
use App\Models\Products\Unit;
use Illuminate\Http\JsonResponse;

class UnitController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $units = Unit::where('status', true)->get();

        return $this->success($units);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $unit = Unit::create([
            'name' => $validated['name'],
            'status' => true,
        ]);

        return $this->created($unit, $unit->name.' unit successfully added.');
    }

    public function show(Unit $unit): JsonResponse
    {
        return $this->success($unit);
    }

    public function update(UpdateRequest $request, Unit $unit): JsonResponse
    {
        $validated = $request->validated();

        $unit->update([
            'name' => $validated['name'],
        ]);

        return $this->success($unit, $unit->name.' unit successfully updated.');
    }

    public function destroy(Unit $unit): JsonResponse
    {
        if (! $unit->status) {
            return $this->forbidden($unit->name.' unit has already been deleted.');
        }

        $unit->update([
            'status' => false,
        ]);

        ItemUnit::where('unit_id', $unit->id)->update([
            'status' => false,
        ]);

        return $this->success(null, $unit->name.' successfully deleted.');
    }
}
