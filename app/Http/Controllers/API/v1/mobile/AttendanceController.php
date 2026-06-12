<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceRecordResource;
use App\Http\Traits\ApiResponse;
use App\Models\Employees\AttendanceRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    use ApiResponse;

    /**
     * List attendance records with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceRecord::with(['user', 'store']);

        // Filter by employee
        if ($request->filled('user_id')) {
            $query->forUser($request->user_id);
        }

        // Filter by store
        if ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'late') {
                $query->where('is_late', true);
            } else {
                $query->where('status', $request->status);
            }
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        // Filter by specific date
        if ($request->filled('date')) {
            $query->forDate($request->date);
        }

        // Search by employee name
        if ($request->filled('search')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%');
            });
        }

        $records = $query->orderBy('date', 'desc')
            ->orderBy('time_in', 'desc')
            ->paginate($request->per_page ?? 30);

        return $this->success([
            'records' => AttendanceRecordResource::collection($records),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    /**
     * Get a single attendance record.
     */
    public function show(AttendanceRecord $attendance): JsonResponse
    {
        $attendance->load(['user', 'store']);

        return $this->success([
            'record' => new AttendanceRecordResource($attendance),
        ]);
    }

    /**
     * Create a new attendance record.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'store_id' => ['required', 'exists:stores,id'],
            'date' => ['required', 'date'],
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i', 'after:time_in'],
            'status' => ['required', 'in:present,absent'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        // Check if record already exists for this user and date
        $existing = AttendanceRecord::forUser($validated['user_id'])
            ->forDate($validated['date'])
            ->first();

        if ($existing) {
            return $this->error('Attendance record already exists for this employee on this date.', 422);
        }

        $attendance = new AttendanceRecord;
        $attendance->user_id = $validated['user_id'];
        $attendance->store_id = $validated['store_id'];
        $attendance->date = $validated['date'];
        $attendance->status = $validated['status'];
        $attendance->remarks = $validated['remarks'] ?? null;

        // Handle time_in
        if (! empty($validated['time_in'])) {
            $attendance->time_in = $validated['date'].' '.$validated['time_in'].':00';
        }

        // Handle time_out
        if (! empty($validated['time_out'])) {
            $attendance->time_out = $validated['date'].' '.$validated['time_out'].':00';
        }

        // Calculate hours if both times are set
        if ($attendance->time_in && $attendance->time_out) {
            $attendance->hours_rendered = $attendance->calculateHours();
        }

        // Need to save first to get the user relationship for late calculation
        $attendance->save();
        $attendance->load(['user', 'store']);

        // Calculate late status based on employee schedule
        $lateData = $attendance->calculateLate();
        $attendance->is_late = $lateData['is_late'];
        $attendance->late_minutes = $lateData['late_minutes'];
        $attendance->save();

        $attendance->notifyLateClockIn();

        return $this->success([
            'record' => new AttendanceRecordResource($attendance),
        ], 'Attendance record created successfully.');
    }

    /**
     * Update an attendance record.
     */
    public function update(Request $request, AttendanceRecord $attendance): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => ['sometimes', 'exists:stores,id'],
            'date' => ['sometimes', 'date'],
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
            'status' => ['sometimes', 'in:present,absent'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        // Check for duplicate if date is being changed
        if (isset($validated['date']) && $validated['date'] !== $attendance->date->toDateString()) {
            $existing = AttendanceRecord::forUser($attendance->user_id)
                ->forDate($validated['date'])
                ->where('id', '!=', $attendance->id)
                ->first();

            if ($existing) {
                return $this->error('Attendance record already exists for this employee on this date.', 422);
            }
        }

        $date = $validated['date'] ?? $attendance->date->toDateString();

        if (isset($validated['store_id'])) {
            $attendance->store_id = $validated['store_id'];
        }

        if (isset($validated['date'])) {
            $attendance->date = $validated['date'];
        }

        if (isset($validated['status'])) {
            $attendance->status = $validated['status'];
        }

        if (array_key_exists('remarks', $validated)) {
            $attendance->remarks = $validated['remarks'];
        }

        // Handle time_in
        if (array_key_exists('time_in', $validated)) {
            $attendance->time_in = $validated['time_in']
                ? $date.' '.$validated['time_in'].':00'
                : null;
        }

        // Handle time_out
        if (array_key_exists('time_out', $validated)) {
            $attendance->time_out = $validated['time_out']
                ? $date.' '.$validated['time_out'].':00'
                : null;
        }

        // Validate time_out is after time_in
        if ($attendance->time_in && $attendance->time_out && $attendance->time_out <= $attendance->time_in) {
            return $this->error('Time out must be after time in.', 422);
        }

        // Calculate hours if both times are set
        if ($attendance->time_in && $attendance->time_out) {
            $attendance->hours_rendered = $attendance->calculateHours();
        } else {
            $attendance->hours_rendered = 0;
        }

        $attendance->save();
        $attendance->load(['user', 'store']);

        // Recalculate late status based on employee schedule
        $lateData = $attendance->calculateLate();
        $attendance->is_late = $lateData['is_late'];
        $attendance->late_minutes = $lateData['late_minutes'];
        $attendance->save();

        return $this->success([
            'record' => new AttendanceRecordResource($attendance),
        ], 'Attendance record updated successfully.');
    }

    /**
     * Delete an attendance record.
     */
    public function destroy(AttendanceRecord $attendance): JsonResponse
    {
        $attendance->delete();

        return $this->success(null, 'Attendance record deleted successfully.');
    }

    /**
     * Get attendance summary/report.
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'user_id' => ['nullable', 'exists:users,id'],
            'store_id' => ['nullable', 'exists:stores,id'],
        ]);

        $query = AttendanceRecord::query()
            ->where('date', '>=', $request->date_from)
            ->where('date', '<=', $request->date_to);

        if ($request->filled('user_id')) {
            $query->forUser($request->user_id);
        }

        if ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        // Get summary by employee
        $summaryByEmployee = DB::table('attendance_records')
            ->join('users', 'attendance_records.user_id', '=', 'users.id')
            ->whereBetween('attendance_records.date', [$request->date_from, $request->date_to])
            ->when($request->filled('user_id'), fn ($q) => $q->where('attendance_records.user_id', $request->user_id))
            ->when($request->filled('store_id'), fn ($q) => $q->where('attendance_records.store_id', $request->store_id))
            ->groupBy('attendance_records.user_id', 'users.name')
            ->select(
                'attendance_records.user_id',
                'users.name as user_name',
                DB::raw('COUNT(*) as total_records'),
                DB::raw("SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) as days_present"),
                DB::raw("SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) as days_absent"),
                DB::raw('SUM(CASE WHEN attendance_records.is_late = 1 THEN 1 ELSE 0 END) as days_late'),
                DB::raw('SUM(attendance_records.hours_rendered) as total_hours'),
                DB::raw('SUM(attendance_records.late_minutes) as total_late_minutes')
            )
            ->orderBy('users.name')
            ->get();

        // Overall totals
        $records = $query->get();
        $totals = [
            'total_records' => $records->count(),
            'days_present' => $records->where('status', 'present')->count(),
            'days_absent' => $records->where('status', 'absent')->count(),
            'days_late' => $records->where('is_late', true)->count(),
            'total_hours' => round($records->sum('hours_rendered'), 2),
            'total_late_minutes' => $records->sum('late_minutes'),
        ];

        return $this->success([
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'totals' => $totals,
            'by_employee' => $summaryByEmployee,
        ]);
    }

    /**
     * Get list of employees for dropdown.
     */
    public function employees(Request $request): JsonResponse
    {
        $query = User::where('status', 1)
            ->whereNotNull('role_id');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $employees = $query->orderBy('name')
            ->select('id', 'name', 'email')
            ->limit(50)
            ->get();

        return $this->success([
            'employees' => $employees,
        ]);
    }
}
