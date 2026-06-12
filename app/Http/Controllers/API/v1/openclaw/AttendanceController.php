<?php

namespace App\Http\Controllers\API\v1\openclaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    use ApiResponse;

    /**
     * GET /v1/openclaw/attendance/records — row-per-day attendance.
     *
     * Lets the bot answer "which dates was Joel late?" by returning each
     * attendance_record row with the employee name resolved. Tenant-scoped
     * via the users.user_id join (attendance_records.user_id is an employee
     * id, not the tenant id).
     */
    public function records(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'user_id' => 'nullable|integer|min:1',
            'store_id' => 'nullable|integer|min:1',
            'only_late' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:2000',
        ]);

        $tenantUserId = (int) auth()->user()->user_id;
        $tz = config('app.timezone');
        $to = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'), $tz)->toDateString()
            : Carbon::today($tz)->toDateString();
        $from = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'), $tz)->toDateString()
            : Carbon::today($tz)->subDays(29)->toDateString();
        $limit = (int) $request->input('limit', 1000);

        $rows = DB::table('attendance_records')
            ->join('users', 'attendance_records.user_id', '=', 'users.id')
            ->where('users.user_id', $tenantUserId)
            ->whereBetween('attendance_records.date', [$from, $to])
            ->when($request->filled('user_id'), fn ($q) => $q->where('attendance_records.user_id', (int) $request->input('user_id')))
            ->when($request->filled('store_id'), fn ($q) => $q->where('attendance_records.store_id', (int) $request->input('store_id')))
            ->when($request->boolean('only_late'), fn ($q) => $q->where('attendance_records.is_late', true))
            ->orderBy('attendance_records.date')
            ->orderBy('users.name')
            ->limit($limit)
            ->select([
                'attendance_records.id',
                'attendance_records.user_id',
                'users.name as employee_name',
                'attendance_records.store_id',
                'attendance_records.date',
                'attendance_records.time_in',
                'attendance_records.time_out',
                'attendance_records.hours_rendered',
                'attendance_records.status',
                'attendance_records.is_late',
                'attendance_records.late_minutes',
                'attendance_records.remarks',
            ])
            ->get();

        return $this->success([
            'date_from' => $from,
            'date_to' => $to,
            'limit' => $limit,
            'count' => $rows->count(),
            'records' => $rows->map(fn ($r) => [
                'id' => (int) $r->id,
                'user_id' => (int) $r->user_id,
                'employee_name' => $r->employee_name,
                'store_id' => $r->store_id !== null ? (int) $r->store_id : null,
                'date' => $r->date,
                'time_in' => $r->time_in,
                'time_out' => $r->time_out,
                'hours_rendered' => round((float) $r->hours_rendered, 2),
                'status' => $r->status,
                'is_late' => (bool) $r->is_late,
                'late_minutes' => (int) $r->late_minutes,
                'remarks' => $r->remarks,
            ])->values(),
        ]);
    }

    /**
     * Tenant-scoped attendance roll-up over a date range.
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'store_id' => 'nullable|integer|min:1',
        ]);

        $tenantUserId = (int) auth()->user()->user_id;
        $tz = config('app.timezone');
        $to = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'), $tz)->toDateString()
            : Carbon::today($tz)->toDateString();
        $from = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'), $tz)->toDateString()
            : Carbon::today($tz)->subDays(29)->toDateString();
        $storeId = $request->filled('store_id') ? (int) $request->input('store_id') : null;

        $base = DB::table('attendance_records')
            ->join('users', 'attendance_records.user_id', '=', 'users.id')
            ->where('users.user_id', $tenantUserId)
            ->whereBetween('attendance_records.date', [$from, $to])
            ->when($storeId !== null, fn ($q) => $q->where('attendance_records.store_id', $storeId));

        $totals = (clone $base)
            ->selectRaw("
                COUNT(*) as total_records,
                SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) as days_present,
                SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) as days_absent,
                SUM(CASE WHEN attendance_records.is_late = 1 THEN 1 ELSE 0 END) as days_late,
                COALESCE(SUM(attendance_records.hours_rendered), 0) as total_hours,
                COALESCE(SUM(attendance_records.late_minutes), 0) as total_late_minutes
            ")
            ->first();

        $byEmployee = (clone $base)
            ->groupBy('attendance_records.user_id', 'users.name')
            ->selectRaw("
                attendance_records.user_id,
                users.name as employee_name,
                COUNT(*) as total_records,
                SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) as days_present,
                SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) as days_absent,
                SUM(CASE WHEN attendance_records.is_late = 1 THEN 1 ELSE 0 END) as days_late,
                COALESCE(SUM(attendance_records.hours_rendered), 0) as total_hours,
                COALESCE(SUM(attendance_records.late_minutes), 0) as total_late_minutes
            ")
            ->orderBy('users.name')
            ->get()
            ->map(fn ($r) => [
                'user_id' => (int) $r->user_id,
                'employee_name' => $r->employee_name,
                'total_records' => (int) $r->total_records,
                'days_present' => (int) $r->days_present,
                'days_absent' => (int) $r->days_absent,
                'days_late' => (int) $r->days_late,
                'total_hours' => round((float) $r->total_hours, 2),
                'total_late_minutes' => (int) $r->total_late_minutes,
            ]);

        return $this->success([
            'date_from' => $from,
            'date_to' => $to,
            'store_id' => $storeId,
            'totals' => [
                'total_records' => (int) ($totals->total_records ?? 0),
                'days_present' => (int) ($totals->days_present ?? 0),
                'days_absent' => (int) ($totals->days_absent ?? 0),
                'days_late' => (int) ($totals->days_late ?? 0),
                'total_hours' => round((float) ($totals->total_hours ?? 0), 2),
                'total_late_minutes' => (int) ($totals->total_late_minutes ?? 0),
            ],
            'by_employee' => $byEmployee,
        ]);
    }
}
