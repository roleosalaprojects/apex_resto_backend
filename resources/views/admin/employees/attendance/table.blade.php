<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>Date</th>
                <th>Employee</th>
                <th>Store</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Hours</th>
                <th>Status</th>
                <th>Late</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $record)
                <tr>
                    <td>{{ $record->date->format('M d, Y') }}</td>
                    <td>{{ $record->user?->name ?? '-' }}</td>
                    <td>{{ $record->store?->name ?? '-' }}</td>
                    <td>{{ $record->time_in?->format('h:i A') ?? '-' }}</td>
                    <td>{{ $record->time_out?->format('h:i A') ?? '-' }}</td>
                    <td>{{ number_format($record->hours_rendered, 2) }}</td>
                    <td>
                        @if($record->status === 'present')
                            <span class="badge bg-success">Present</span>
                        @else
                            <span class="badge bg-danger">Absent</span>
                        @endif
                    </td>
                    <td>
                        @if($record->is_late)
                            <span class="badge bg-warning text-dark" title="{{ $record->late_minutes }} minutes late">
                                {{ $record->late_minutes }} min
                            </span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="{{ route('attendance.show', $record) }}" class="btn btn-sm btn-icon btn-light-primary" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="{{ route('attendance.edit', $record) }}" class="btn btn-sm btn-icon btn-light-info" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('attendance.destroy', $record) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this record?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-icon btn-light-danger" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">No attendance records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="d-flex justify-content-end mt-3">
    {{ $records->links() }}
</div>
