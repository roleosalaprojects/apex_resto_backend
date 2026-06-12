<?php

namespace App\Http\Controllers\API\v1\openclaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /v1/openclaw/cash-outs — list cash-out events from pos_logs.
 *
 * pos_logs.type 12 = Cash-Out, type 13 = Void Cash-Out. A void carries
 * the voided cash-out's id in so_id, so the active set is "type=12 rows
 * whose id is not referenced by any type=13 row".
 *
 * Note: pos_logs.user_id is the CASHIER (the employee who recorded the
 * cash-out), not the tenant. Tenant scoping is therefore done via a
 * join to users.user_id, same pattern as attendance_records.
 */
class CashOutController extends Controller
{
    use ApiResponse;

    private const TYPE_CASH_OUT = 12;

    private const TYPE_VOID_CASH_OUT = 13;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'store_id' => 'nullable|integer|min:1',
            'pos_id' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:2000',
        ]);

        $tenantUserId = (int) auth()->user()->user_id;
        $tz = config('app.timezone');
        $to = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'), $tz)->endOfDay()
            : Carbon::today($tz)->endOfDay();
        $from = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'), $tz)->startOfDay()
            : (clone $to)->subDays(29)->startOfDay();
        $limit = (int) $request->input('limit', 1000);

        // Subquery: ids of cash-outs that have been voided. Tenant scope
        // is applied via the cashier-user join, not pos_logs.user_id.
        $voidedIds = DB::table('pos_logs')
            ->join('users', 'pos_logs.user_id', '=', 'users.id')
            ->where('users.user_id', $tenantUserId)
            ->where('pos_logs.type', self::TYPE_VOID_CASH_OUT)
            ->whereNotNull('pos_logs.so_id')
            ->pluck('pos_logs.so_id');

        $rows = DB::table('pos_logs')
            ->join('users', 'pos_logs.user_id', '=', 'users.id')
            ->where('users.user_id', $tenantUserId)
            ->where('pos_logs.type', self::TYPE_CASH_OUT)
            ->whereNotIn('pos_logs.id', $voidedIds)
            ->whereBetween('pos_logs.created_at', [$from, $to])
            ->when($request->filled('store_id'), fn ($q) => $q->where('pos_logs.store_id', (int) $request->input('store_id')))
            ->when($request->filled('pos_id'), fn ($q) => $q->where('pos_logs.pos_id', (int) $request->input('pos_id')))
            ->orderBy('pos_logs.created_at')
            ->limit($limit)
            ->select([
                'pos_logs.id',
                'pos_logs.cash_out',
                'pos_logs.reason',
                'pos_logs.so_id',
                'pos_logs.store_id',
                'pos_logs.pos_id',
                'pos_logs.created_at',
                'users.name as employee_name',
            ])
            ->get();

        return $this->success([
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'limit' => $limit,
            'count' => $rows->count(),
            'totals' => [
                'amount' => round((float) $rows->sum('cash_out'), 2),
            ],
            'cash_outs' => $rows->map(fn ($r) => [
                'id' => (int) $r->id,
                'date' => $r->created_at ? Carbon::parse($r->created_at)->setTimezone($tz)->toDateString() : null,
                'amount' => round((float) $r->cash_out, 2),
                'reason' => $r->reason,
                'employee_name' => $r->employee_name,
                'store_id' => $r->store_id !== null ? (int) $r->store_id : null,
                'pos_id' => $r->pos_id !== null ? (int) $r->pos_id : null,
                'so_id' => $r->so_id !== null ? (int) $r->so_id : null,
                'created_at' => $r->created_at ? Carbon::parse($r->created_at)->toIso8601String() : null,
            ])->values(),
        ]);
    }
}
