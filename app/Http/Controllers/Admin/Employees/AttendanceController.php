<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Attendance\StoreRequest;
use App\Http\Requests\Admin\Attendance\UpdateRequest;
use App\Models\Employees\AttendanceRecord;
use App\Models\Employees\Role;
use App\Models\Reports\AuditLog;
use App\Models\Settings\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Exceptions\Exception;

class AttendanceController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $access = Role::find(auth()->user()->role_id);

        if (! $access->attndnc) {
            return redirect()->route('admin.home')
                ->with('error', "You don't have rights to access this.");
        }

        $stores = Store::where('user_id', auth()->user()->user_id)
            ->where('status', true)
            ->get();

        $employees = User::where('user_id', auth()->user()->user_id)
            ->where('status', true)
            ->get();

        return view('admin.employees.attendance.index', compact('access', 'stores', 'employees'));
    }

    public function table(Request $request): JsonResponse
    {
        $access = Role::find(auth()->user()->role_id);

        $query = $this->filteredQuery($request);

        try {
            return DataTables($query)
                ->addColumn('formatted_date', function ($record) {
                    return $record->date->format('M d, Y');
                })
                ->addColumn('employee_name', function ($record) {
                    return $record->user?->name ?? '<span class="text-muted">-</span>';
                })
                ->addColumn('store_name', function ($record) {
                    return $record->store?->name ?? '<span class="text-muted">-</span>';
                })
                ->addColumn('time_in_formatted', function ($record) {
                    return $record->time_in?->format('h:i A') ?? '<span class="text-muted">-</span>';
                })
                ->addColumn('time_out_formatted', function ($record) {
                    return $record->time_out?->format('h:i A') ?? '<span class="text-muted">-</span>';
                })
                ->addColumn('formatted_hours', function ($record) {
                    return number_format($record->hours_rendered, 2);
                })
                ->addColumn('status_badge', function ($record) {
                    if ($record->status === 'present' && $record->is_late) {
                        return '<span class="badge badge-light-warning">Late</span>';
                    }

                    if ($record->status === 'present') {
                        return '<span class="badge badge-light-success">Present</span>';
                    }

                    return '<span class="badge badge-light-danger">Absent</span>';
                })
                ->addColumn('late_badge', function ($record) {
                    if ($record->is_late) {
                        return '<span class="badge badge-light-warning">'.$record->late_minutes.' min</span>';
                    }

                    return '<span class="text-muted">-</span>';
                })
                ->addColumn('actions', function ($record) use ($access) {
                    $html = '<div class="d-flex gap-1">';

                    if ($access->attndnc_read) {
                        $html .= '<a href="'.route('attendance.show', $record).'" class="btn btn-sm btn-icon btn-light-primary" title="View"><i class="fas fa-eye"></i></a>';
                    }

                    if ($access->attndnc_update) {
                        $html .= '<a href="'.route('attendance.edit', $record).'" class="btn btn-sm btn-icon btn-light-info" title="Edit"><i class="fas fa-edit"></i></a>';
                    }

                    if ($access->attndnc_delete) {
                        $html .= '<form action="'.route('attendance.destroy', $record).'" method="POST" class="d-inline" onsubmit="return confirm(\'Are you sure you want to delete this record?\')">'.csrf_field().method_field('DELETE').'<button type="submit" class="btn btn-sm btn-icon btn-light-danger" title="Delete"><i class="fas fa-trash"></i></button></form>';
                    }

                    $html .= '</div>';

                    return $html;
                })
                ->rawColumns(['employee_name', 'store_name', 'time_in_formatted', 'time_out_formatted', 'status_badge', 'late_badge', 'actions'])
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        $access = Role::find(auth()->user()->role_id);

        if (! $access->attndnc) {
            return redirect()->route('admin.home')
                ->with('error', "You don't have rights to access this.");
        }

        $query = $this->filteredQuery($request)->latest('date')->latest('id');

        $filename = 'attendance_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Date', 'Employee', 'Store', 'Time In', 'Time Out', 'Hours', 'Status', 'Late (min)']);

            foreach ($query->lazy() as $record) {
                $status = $record->status === 'present'
                    ? ($record->is_late ? 'Late' : 'Present')
                    : 'Absent';

                fputcsv($handle, [
                    $record->date->format('M d, Y'),
                    $record->user?->name ?? '-',
                    $record->store?->name ?? '-',
                    $record->time_in?->format('h:i A') ?? '-',
                    $record->time_out?->format('h:i A') ?? '-',
                    number_format($record->hours_rendered, 2, '.', ''),
                    $status,
                    $record->is_late ? $record->late_minutes : '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = AttendanceRecord::query()
            ->with(['user', 'store'])
            ->whereHas('user', function ($q) {
                $q->where('user_id', auth()->user()->user_id);
            });

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('status')) {
            if ($request->input('status') === 'late') {
                $query->where('is_late', true);
            } else {
                $query->where('status', $request->input('status'));
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->input('date_to'));
        }

        return $query;
    }

    public function create(): View|RedirectResponse
    {
        $access = Role::find(auth()->user()->role_id);

        if (! $access->attndnc_create) {
            return redirect()->route('attendance.index')
                ->with('error', "You don't have rights to create attendance records.");
        }

        $stores = Store::where('user_id', auth()->user()->user_id)
            ->where('status', true)
            ->get();

        $employees = User::where('user_id', auth()->user()->user_id)
            ->where('status', true)
            ->get();

        return view('admin.employees.attendance.create', compact('access', 'stores', 'employees'));
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $access = Role::find(auth()->user()->role_id);

        if (! $access->attndnc_create) {
            return redirect()->route('attendance.index')
                ->with('error', "You don't have rights to create attendance records.");
        }

        $timeIn = $request->time_in ? Carbon::parse($request->date.' '.$request->time_in) : null;
        $timeOut = $request->time_out ? Carbon::parse($request->date.' '.$request->time_out) : null;

        $hoursRendered = 0;
        if ($timeIn && $timeOut) {
            $hoursRendered = round($timeIn->diffInMinutes($timeOut) / 60, 2);
        }

        $record = AttendanceRecord::create([
            'user_id' => $request->user_id,
            'store_id' => $request->store_id,
            'date' => $request->date,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'hours_rendered' => $hoursRendered,
            'status' => $request->status,
            'remarks' => $request->remarks,
        ]);

        // Calculate late status based on employee schedule
        $record->load(['user', 'store']);
        $lateData = $record->calculateLate();
        $record->update([
            'is_late' => $lateData['is_late'],
            'late_minutes' => $lateData['late_minutes'],
        ]);

        $record->notifyLateClockIn();

        // Create audit log
        $this->createAuditLog($record, 'created', [], $record->toArray());

        return redirect()->route('attendance.index')
            ->with('msg', 'Attendance record created successfully.');
    }

    public function show(AttendanceRecord $attendance): View|RedirectResponse
    {
        $access = Role::find(auth()->user()->role_id);

        if (! $access->attndnc_read) {
            return redirect()->route('attendance.index')
                ->with('error', "You don't have rights to view attendance records.");
        }

        $attendance->load(['user', 'store']);

        // Get audit logs for this record
        $auditLogs = AuditLog::where('auditable_type', AttendanceRecord::class)
            ->where('auditable_id', $attendance->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.employees.attendance.show', compact('attendance', 'access', 'auditLogs'));
    }

    public function edit(AttendanceRecord $attendance): View|RedirectResponse
    {
        $access = Role::find(auth()->user()->role_id);

        if (! $access->attndnc_update) {
            return redirect()->route('attendance.index')
                ->with('error', "You don't have rights to edit attendance records.");
        }

        $attendance->load(['user', 'store']);

        $stores = Store::where('user_id', auth()->user()->user_id)
            ->where('status', true)
            ->get();

        $employees = User::where('user_id', auth()->user()->user_id)
            ->where('status', true)
            ->get();

        return view('admin.employees.attendance.edit', compact('attendance', 'access', 'stores', 'employees'));
    }

    public function update(UpdateRequest $request, AttendanceRecord $attendance): RedirectResponse
    {
        $access = Role::find(auth()->user()->role_id);

        if (! $access->attndnc_update) {
            return redirect()->route('attendance.index')
                ->with('error', "You don't have rights to edit attendance records.");
        }

        $oldValues = $attendance->toArray();

        $timeIn = $request->time_in ? Carbon::parse($request->date.' '.$request->time_in) : null;
        $timeOut = $request->time_out ? Carbon::parse($request->date.' '.$request->time_out) : null;

        $hoursRendered = 0;
        if ($timeIn && $timeOut) {
            $hoursRendered = round($timeIn->diffInMinutes($timeOut) / 60, 2);
        }

        $attendance->update([
            'user_id' => $request->user_id,
            'store_id' => $request->store_id,
            'date' => $request->date,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'hours_rendered' => $hoursRendered,
            'status' => $request->status,
            'remarks' => $request->remarks,
        ]);

        // Recalculate late status based on employee schedule
        $attendance->load(['user', 'store']);
        $lateData = $attendance->calculateLate();
        $attendance->update([
            'is_late' => $lateData['is_late'],
            'late_minutes' => $lateData['late_minutes'],
        ]);

        $attendance->notifyLateClockIn();

        // Create audit log
        $this->createAuditLog($attendance, 'updated', $oldValues, $attendance->fresh()->toArray());

        return redirect()->route('attendance.show', $attendance)
            ->with('msg', 'Attendance record updated successfully.');
    }

    public function destroy(AttendanceRecord $attendance): RedirectResponse
    {
        $access = Role::find(auth()->user()->role_id);

        if (! $access->attndnc_delete) {
            return redirect()->route('attendance.index')
                ->with('error', "You don't have rights to delete attendance records.");
        }

        $oldValues = $attendance->toArray();

        // Create audit log before deletion
        $this->createAuditLog($attendance, 'deleted', $oldValues, []);

        $attendance->delete();

        return redirect()->route('attendance.index')
            ->with('msg', 'Attendance record deleted successfully.');
    }

    public function auditLog(AttendanceRecord $attendance): JsonResponse
    {
        $logs = AuditLog::where('auditable_type', AttendanceRecord::class)
            ->where('auditable_id', $attendance->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($logs);
    }

    public function calendarEvents(Request $request): JsonResponse
    {
        $query = AttendanceRecord::query()
            ->with(['user', 'store'])
            ->whereHas('user', function ($q) {
                $q->where('user_id', auth()->user()->user_id);
            });

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('start')) {
            $query->whereDate('date', '>=', $request->input('start'));
        }

        if ($request->filled('end')) {
            $query->whereDate('date', '<=', $request->input('end'));
        }

        $events = $query->get()->map(function (AttendanceRecord $record) {
            $isLate = $record->is_late && $record->status === 'present';
            $statusLabel = $record->status === 'present' ? ($isLate ? 'Late' : 'Present') : 'Absent';

            if ($record->status === 'present') {
                $color = $isLate ? config('colors.warning') : config('colors.success');
            } else {
                $color = config('colors.danger');
            }

            return [
                'id' => $record->id,
                'title' => ($record->user?->name ?? 'Unknown').' - '.$statusLabel,
                'start' => $record->date->format('Y-m-d'),
                'allDay' => true,
                'color' => $color,
                'extendedProps' => [
                    'status' => $record->status,
                    'employee' => $record->user?->name,
                    'store' => $record->store?->name,
                    'time_in' => $record->time_in?->format('h:i A'),
                    'time_out' => $record->time_out?->format('h:i A'),
                    'hours' => number_format((float) $record->hours_rendered, 2),
                    'is_late' => $record->is_late,
                    'late_minutes' => $record->late_minutes,
                    'remarks' => $record->remarks,
                ],
            ];
        });

        return response()->json($events->values());
    }

    public function summary(Request $request): View|RedirectResponse
    {
        $access = Role::find(auth()->user()->role_id);

        if (! $access->attndnc) {
            return redirect()->route('admin.home')
                ->with('error', "You don't have rights to access this.");
        }

        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->toDateString());
        $storeId = $request->get('store_id');

        $query = User::where('user_id', auth()->user()->user_id)
            ->where('status', true)
            ->with(['attendanceRecords' => function ($q) use ($startDate, $endDate, $storeId) {
                $q->whereBetween('date', [$startDate, $endDate]);
                if ($storeId) {
                    $q->where('store_id', $storeId);
                }
            }]);

        $employees = $query->get();

        $stores = Store::where('user_id', auth()->user()->user_id)
            ->where('status', true)
            ->get();

        return view('admin.employees.attendance.summary', compact('employees', 'stores', 'access', 'startDate', 'endDate', 'storeId'));
    }

    private function createAuditLog(AttendanceRecord $record, string $event, array $oldValues, array $newValues): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'auditable_type' => AttendanceRecord::class,
            'auditable_id' => $record->id,
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
        ]);
    }
}
