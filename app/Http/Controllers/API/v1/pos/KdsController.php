<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Pos\Order;
use App\Models\Pos\OrderLine;
use App\Models\Restaurant\KitchenStation;
use App\Services\Restaurant\KdsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KdsController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly KdsService $kds) {}

    public function stations(): JsonResponse
    {
        $userId = Auth::guard('api')->user()->user_id;

        $stations = KitchenStation::where('user_id', $userId)
            ->where('status', true)
            ->get(['id', 'name', 'store_id']);

        return $this->success($stations);
    }

    public function queue(Request $request, KitchenStation $station): JsonResponse
    {
        $lines = $this->kds->queueForStation($station->id, $request->query('updated_since'));

        return $this->success([
            'station_id' => $station->id,
            'server_time' => now()->toIso8601String(),
            'lines' => $lines,
        ]);
    }

    public function bumpLine(OrderLine $line): JsonResponse
    {
        $line = $this->kds->bumpLine($line, Auth::guard('api')->id());

        return $this->success($line);
    }

    public function bumpOrder(Order $order): JsonResponse
    {
        $this->kds->bumpOrder($order, Auth::guard('api')->id());

        return $this->success($order->load('lines'));
    }
}
