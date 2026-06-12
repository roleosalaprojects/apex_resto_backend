<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeSchedule\UpdateRequest;
use App\Models\Employees\EmployeeSchedule;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmployeeScheduleController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $access = Role::find(auth()->user()->role_id);

        if (! $access->attndnc_schedules) {
            return redirect()->route('admin.home')
                ->with('error', "You don't have rights to access this.");
        }

        $employees = User::where('user_id', auth()->user()->user_id)
            ->where('status', true)
            ->with('employeeSchedules')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.employees.schedules.index', compact('access', 'employees'));
    }

    public function table(Request $request): View
    {
        $query = User::where('user_id', auth()->user()->user_id)
            ->where('status', true)
            ->with('employeeSchedules');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $employees = $query->orderBy('name')->paginate(20);

        return view('admin.employees.schedules.table', compact('employees'));
    }

    public function edit(User $schedule): View|RedirectResponse
    {
        $access = Role::find(auth()->user()->role_id);

        if (! $access->attndnc_schedules) {
            return redirect()->route('schedules.index')
                ->with('error', "You don't have rights to edit schedules.");
        }

        $employee = $schedule;
        $employee->load('employeeSchedules');

        // Build array of schedules indexed by day_of_week
        $schedules = [];
        foreach (EmployeeSchedule::DAY_NAMES as $dayIndex => $dayName) {
            $existingSchedule = $employee->employeeSchedules->firstWhere('day_of_week', $dayIndex);
            $schedules[$dayIndex] = [
                'day_name' => $dayName,
                'start_time' => $existingSchedule?->start_time?->format('H:i'),
                'is_rest_day' => $existingSchedule === null || $existingSchedule->start_time === null,
            ];
        }

        return view('admin.employees.schedules.edit', compact('access', 'employee', 'schedules'));
    }

    public function update(UpdateRequest $request, User $schedule): RedirectResponse
    {
        $access = Role::find(auth()->user()->role_id);

        if (! $access->attndnc_schedules) {
            return redirect()->route('schedules.index')
                ->with('error', "You don't have rights to edit schedules.");
        }

        $employee = $schedule;

        foreach ($request->validated()['schedules'] as $dayOfWeek => $scheduleData) {
            $startTime = null;
            if (! isset($scheduleData['is_rest_day']) && ! empty($scheduleData['start_time'])) {
                $startTime = $scheduleData['start_time'];
            }

            EmployeeSchedule::updateOrCreate(
                [
                    'user_id' => $employee->id,
                    'day_of_week' => $dayOfWeek,
                ],
                [
                    'start_time' => $startTime,
                ]
            );
        }

        return redirect()->route('schedules.index')
            ->with('msg', "Schedule for {$employee->name} updated successfully.");
    }
}
