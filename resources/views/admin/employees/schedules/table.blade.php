<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Sun</th>
                <th>Mon</th>
                <th>Tue</th>
                <th>Wed</th>
                <th>Thu</th>
                <th>Fri</th>
                <th>Sat</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($employees as $employee)
                <tr>
                    <td>{{ $employee->name }}</td>
                    @for($day = 0; $day < 7; $day++)
                        @php
                            $schedule = $employee->employeeSchedules->firstWhere('day_of_week', $day);
                        @endphp
                        <td>
                            @if($schedule && $schedule->start_time)
                                <span class="badge bg-light-primary text-primary">
                                    {{ $schedule->formatted_start_time }}
                                </span>
                            @else
                                <span class="badge bg-light-secondary text-muted">Rest</span>
                            @endif
                        </td>
                    @endfor
                    <td>
                        <a href="{{ route('schedules.edit', $employee) }}" class="btn btn-sm btn-icon btn-light-info" title="Edit Schedule">
                            <i class="fas fa-edit"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">No employees found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="d-flex justify-content-end mt-3">
    {{ $employees->links() }}
</div>
