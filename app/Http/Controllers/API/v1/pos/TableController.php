<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Restaurant\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TableController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $userId = Auth::guard('api')->user()->user_id;

        $tables = RestaurantTable::query()
            ->where('user_id', $userId)
            ->when($request->filled('store_id'), fn ($q) => $q->where('store_id', $request->integer('store_id')))
            ->with(['openOrder' => function ($q) {
                $q->select('id', 'table_id', 'reference', 'pax', 'amount', 'status');
            }])
            ->orderBy('area')
            ->orderBy('name')
            ->get()
            ->map(function (RestaurantTable $table) {
                $open = $table->openOrder->first();

                return [
                    'id' => $table->id,
                    'name' => $table->name,
                    'number' => $table->number,
                    'area' => $table->area,
                    'seats' => $table->seats,
                    'status' => $table->status,
                    'open_order' => $open ? [
                        'id' => $open->id,
                        'reference' => $open->reference,
                        'pax' => $open->pax,
                        'amount' => $open->amount,
                    ] : null,
                ];
            });

        return $this->success($tables);
    }
}
