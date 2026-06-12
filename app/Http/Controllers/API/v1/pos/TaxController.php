<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\StoreRequest;
use App\Http\Requests\Tax\UpdateRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Settings\Tax;
use Illuminate\Http\JsonResponse;

class TaxController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $taxes = Tax::where('status', true)->get();

        return $this->success($taxes);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $tax = Tax::create([
            'name' => $validated['name'],
            'rate' => $validated['rate'],
            'status' => true,
        ]);

        return $this->created($tax, $tax->name.' tax created');
    }

    public function show(Tax $tax): JsonResponse
    {
        return $this->success($tax);
    }

    public function update(UpdateRequest $request, Tax $tax): JsonResponse
    {
        $validated = $request->validated();

        $tax->update([
            'name' => $validated['name'],
            'rate' => $validated['rate'],
        ]);

        return $this->success($tax, $tax->name.' tax successfully updated.');
    }

    public function destroy(Tax $tax): JsonResponse
    {
        if (! $tax->status) {
            return $this->forbidden($tax->name.' tax has already been deleted.');
        }

        $tax->update([
            'status' => false,
        ]);

        return $this->success(null, $tax->name.' tax successfully deleted.');
    }
}
