<?php

namespace App\Http\Controllers\API\v1\desktop;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceRecordResource;
use App\Http\Traits\ApiResponse;
use App\Models\Employees\AttendanceRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use ApiResponse;

    /**
     * Record employee time-in via barcode scan.
     */
    public function timeIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string'],
            'store_id' => ['required', 'exists:stores,id'],
        ]);

        // Find employee by barcode (user's uniqid)
        $user = User::where('code', $validated['barcode'])->first();

        if (! $user) {
            return $this->error('Employee not found. Invalid barcode.', 404);
        }

        $today = now()->toDateString();

        // Check if already timed in today
        $existing = AttendanceRecord::forUser($user->id)
            ->forDate($today)
            ->first();

        if ($existing && $existing->hasTimedIn()) {
            return $this->error('Already timed in today at '.$existing->time_in->format('h:i A'), 400);
        }

        // Create or update attendance record
        $attendance = AttendanceRecord::updateOrCreate(
            ['user_id' => $user->id, 'date' => $today],
            [
                'store_id' => $validated['store_id'],
                'time_in' => now(),
                'status' => 'present',
            ]
        );

        // Calculate late status based on employee schedule
        $attendance->load(['user', 'store']);
        $lateData = $attendance->calculateLate();
        $attendance->update([
            'is_late' => $lateData['is_late'],
            'late_minutes' => $lateData['late_minutes'],
        ]);

        $attendance->notifyLateClockIn();

        return $this->success([
            'attendance' => new AttendanceRecordResource($attendance),
            'employee_name' => $user->name,
        ], 'Time-in recorded for '.$user->name);
    }

    /**
     * Record employee time-out via barcode scan.
     */
    public function timeOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string'],
        ]);

        // Find employee by barcode
        $user = User::where('code', $validated['barcode'])->first();

        if (! $user) {
            return $this->error('Employee not found. Invalid barcode.', 404);
        }

        $today = now()->toDateString();

        $attendance = AttendanceRecord::forUser($user->id)
            ->forDate($today)
            ->first();

        if (! $attendance || ! $attendance->hasTimedIn()) {
            return $this->error('No time-in record found for today. Please time-in first.', 400);
        }

        if ($attendance->hasTimedOut()) {
            return $this->error('Already timed out today at '.$attendance->time_out->format('h:i A'), 400);
        }

        $attendance->update([
            'time_out' => now(),
        ]);

        // Calculate hours after update
        $attendance->hours_rendered = $attendance->calculateHours();
        $attendance->save();

        $attendance->load(['user', 'store']);

        return $this->success([
            'attendance' => new AttendanceRecordResource($attendance),
            'employee_name' => $user->name,
        ], 'Time-out recorded for '.$user->name.'. Total hours: '.number_format($attendance->hours_rendered, 2));
    }

    /**
     * Get today's attendance record for an employee via barcode.
     */
    public function today(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string'],
        ]);

        $user = User::where('code', $validated['barcode'])->first();

        if (! $user) {
            return $this->error('Employee not found. Invalid barcode.', 404);
        }

        $attendance = AttendanceRecord::forUser($user->id)
            ->forDate(now()->toDateString())
            ->with(['user', 'store'])
            ->first();

        return $this->success([
            'attendance' => $attendance ? new AttendanceRecordResource($attendance) : null,
            'employee' => [
                'id' => $user->id,
                'name' => $user->name,
                'barcode' => $user->code,
            ],
        ]);
    }

    /**
     * Get attendance history for an employee.
     */
    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $user = User::where('code', $validated['barcode'])->first();

        if (! $user) {
            return $this->error('Employee not found. Invalid barcode.', 404);
        }

        $query = AttendanceRecord::forUser($user->id)
            ->with(['store']);

        if (! empty($validated['from'])) {
            $query->where('date', '>=', $validated['from']);
        }

        if (! empty($validated['to'])) {
            $query->where('date', '<=', $validated['to']);
        }

        $records = $query->orderBy('date', 'desc')
            ->paginate(30);

        return $this->success([
            'records' => AttendanceRecordResource::collection($records),
            'employee' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    /**
     * Get monthly summary for an employee.
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2020'],
        ]);

        $user = User::where('code', $validated['barcode'])->first();

        if (! $user) {
            return $this->error('Employee not found. Invalid barcode.', 404);
        }

        $month = $validated['month'] ?? now()->month;
        $year = $validated['year'] ?? now()->year;

        $records = AttendanceRecord::forUser($user->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get();

        return $this->success([
            'month' => $month,
            'year' => $year,
            'employee' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'total_days_present' => $records->where('status', 'present')->count(),
            'total_days_absent' => $records->where('status', 'absent')->count(),
            'total_hours_rendered' => round($records->sum('hours_rendered'), 2),
        ]);
    }

    /**
     * Lookup employee by barcode (for display before time-in/out).
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string'],
        ]);

        $user = User::where('code', $validated['barcode'])->first();

        if (! $user) {
            return $this->error('Employee not found. Invalid barcode.', 404);
        }

        $todayAttendance = AttendanceRecord::forUser($user->id)
            ->forDate(now()->toDateString())
            ->first();

        return $this->success([
            'employee' => [
                'id' => $user->id,
                'name' => $user->name,
                'barcode' => $user->code,
            ],
            'today_status' => $todayAttendance ? [
                'has_timed_in' => $todayAttendance->hasTimedIn(),
                'has_timed_out' => $todayAttendance->hasTimedOut(),
                'time_in' => $todayAttendance->time_in?->format('h:i A'),
                'time_out' => $todayAttendance->time_out?->format('h:i A'),
                'hours_rendered' => $todayAttendance->hours_rendered,
            ] : null,
        ]);
    }
}
