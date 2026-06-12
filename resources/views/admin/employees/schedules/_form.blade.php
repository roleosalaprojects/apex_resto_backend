@php
    use App\Models\Employees\EmployeeSchedule;
@endphp

<div class="table-responsive">
    <table class="table table-row-bordered">
        <thead>
            <tr>
                <th class="fw-bold">Day</th>
                <th class="fw-bold">Start Time</th>
                <th class="fw-bold text-center">Rest Day</th>
            </tr>
        </thead>
        <tbody>
            @foreach(EmployeeSchedule::DAY_NAMES as $dayIndex => $dayName)
                <tr class="schedule-row">
                    <td class="align-middle fw-semibold">{{ $dayName }}</td>
                    <td>
                        <input
                            type="time"
                            name="schedules[{{ $dayIndex }}][start_time]"
                            class="form-control start-time-input @error('schedules.' . $dayIndex . '.start_time') is-invalid @enderror"
                            value="{{ old('schedules.' . $dayIndex . '.start_time', $schedules[$dayIndex]['start_time'] ?? '') }}"
                        >
                        @error('schedules.' . $dayIndex . '.start_time')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </td>
                    <td class="text-center align-middle">
                        <div class="form-check form-check-custom form-check-solid justify-content-center">
                            <input
                                type="checkbox"
                                name="schedules[{{ $dayIndex }}][is_rest_day]"
                                value="1"
                                class="form-check-input rest-day-checkbox"
                                {{ old('schedules.' . $dayIndex . '.is_rest_day', $schedules[$dayIndex]['is_rest_day'] ?? false) ? 'checked' : '' }}
                            >
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
