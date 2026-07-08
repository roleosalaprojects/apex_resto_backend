<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Settings\Pos;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PosTerminalController extends Controller
{
    use ApiResponse;

    /**
     * The tenant's POS terminals. A cashier station uses this to run
     * X/Z readings for any terminal — restaurant sales land on the
     * terminal the ORDER was opened on (usually a waiter device), not
     * on the cashier's own terminal.
     */
    public function index(): JsonResponse
    {
        $terminals = Pos::query()
            ->where('user_id', Auth::guard('api')->user()->user_id)
            ->with('store:id,name')
            ->orderBy('number')
            ->get(['id', 'name', 'number', 'store_id', 'status']);

        return $this->success($terminals);
    }
}
