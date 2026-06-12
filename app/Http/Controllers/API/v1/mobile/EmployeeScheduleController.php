<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Employees\EmployeeSchedule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeScheduleController extends Controller
{
    use ApiResponse;

    /**
     * Get employee's weekly schedule.
     */
    public function show(User $user): JsonResponse
    {
        $schedules = $user->employeeSchedules()
            ->orderBy('day_of_week')
            ->get()
            ->keyBy('day_of_week');

        // Build full week with all days
        $weekSchedule = [];
        for ($day = 0; $day <= 6; $day++) {
            $schedule = $schedules->get($day);
            $weekSchedule[] = [
                'day_of_week' => $day,
                'day_name' => EmployeeSchedule::DAY_NAMES[$day],
                'start_time' => $schedule?->start_time?->format('H:i'),
                'formatted_start_time' => $schedule?->formatted_start_time,
            ];
        }

        return $this->success([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'grace_period' => config('attendance.grace_period', 5),
            'schedules' => $weekSchedule,
        ]);
    }

    /**
     * Update employee's weekly schedule.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'schedules' => ['required', 'array'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'schedules.*.start_time' => ['nullable', 'date_format:H:i'],
        ]);

        // Clear existing schedules and create new ones
        $user->employeeSchedules()->delete();

        foreach ($validated['schedules'] as $scheduleData) {
            if (! empty($scheduleData['start_time'])) {
                EmployeeSchedule::create([
                    'user_id' => $user->id,
                    'day_of_week' => $scheduleData['day_of_week'],
                    'start_time' => $scheduleData['start_time'],
                ]);
            }
        }

        // Return updated schedule
        return $this->show($user);
    }
}
