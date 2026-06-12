<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>User</th>
                <th>Source</th>
                <th>Event</th>
                <th>Model</th>
                <th>Record ID</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at->format('M d, Y h:i A') }}</td>
                    <td>{{ $log->user?->name ?? 'System' }}</td>
                    <td>
                        @switch($log->source)
                            @case('web')
                                <span class="badge bg-primary">Web</span>
                                @break
                            @case('openclaw')
                                <span class="badge bg-info">OpenClaw</span>
                                @if ($log->apiToken)
                                    <small class="text-muted d-block">{{ $log->apiToken->name }}</small>
                                @endif
                                @break
                            @case('mobile')
                                <span class="badge bg-success">Mobile</span>
                                @break
                            @case('pos')
                                <span class="badge bg-warning text-dark">POS</span>
                                @break
                            @case('console')
                                <span class="badge bg-dark">Console</span>
                                @break
                            @default
                                <span class="badge bg-secondary">{{ $log->source ?? '—' }}</span>
                        @endswitch
                    </td>
                    <td>
                        @switch($log->event)
                            @case('created')
                                <span class="badge bg-success">Created</span>
                                @break
                            @case('updated')
                                <span class="badge bg-warning text-dark">Updated</span>
                                @break
                            @case('deleted')
                                <span class="badge bg-danger">Deleted</span>
                                @break
                            @case('voided')
                                <span class="badge bg-dark">Voided</span>
                                @break
                            @case('refunded')
                                <span class="badge bg-info">Refunded</span>
                                @break
                            @case('approved')
                                <span class="badge bg-primary">Approved</span>
                                @break
                            @case('rejected')
                                <span class="badge bg-secondary">Rejected</span>
                                @break
                            @default
                                <span class="badge bg-light text-dark">{{ ucfirst($log->event) }}</span>
                        @endswitch
                    </td>
                    <td>{{ class_basename($log->auditable_type) }}</td>
                    <td>{{ $log->auditable_id }}</td>
                    <td>
                        <a href="{{ route('audit_logs.show', $log) }}" class="btn btn-sm btn-info">View Details</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No audit logs found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $logs->links() }}
