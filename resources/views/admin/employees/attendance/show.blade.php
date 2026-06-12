@extends('layout.app')
@section('header')
    - Attendance Details
@endsection
@section('title')
    Attendance Details
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('attendance.index') }}">Attendance</a></li>
    <li class="breadcrumb-item text-muted">Details</li>
@endsection
@section('actions')
    @if($access->emplys_update)
        <a href="{{ route('attendance.edit', $attendance) }}" class="btn btn-primary">
            <i class="fas fa-edit me-1"></i> Edit
        </a>
    @endif
@endsection
@section('content')
    @if(session('msg'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('msg') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-5">
                <div class="card-header">
                    <h4 class="card-title">Attendance Information</h4>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th class="text-muted w-25">Employee</th>
                            <td>{{ $attendance->user?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Store</th>
                            <td>{{ $attendance->store?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Date</th>
                            <td>{{ $attendance->date->format('F d, Y') }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Time In</th>
                            <td>{{ $attendance->time_in?->format('h:i A') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Time Out</th>
                            <td>{{ $attendance->time_out?->format('h:i A') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Hours Rendered</th>
                            <td>{{ number_format($attendance->hours_rendered, 2) }} hours</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Status</th>
                            <td>
                                @if($attendance->status === 'present')
                                    <span class="badge bg-light-success text-success">Present</span>
                                @else
                                    <span class="badge bg-light-danger text-danger">Absent</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted">Remarks</th>
                            <td>{{ $attendance->remarks ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Created</th>
                            <td>{{ $attendance->created_at->format('M d, Y h:i A') }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Updated</th>
                            <td>{{ $attendance->updated_at->format('M d, Y h:i A') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="fas fa-history me-2"></i> Change History
                    </h4>
                </div>
                <div class="card-body">
                    @if($auditLogs->isEmpty())
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                            <p>No changes recorded yet.</p>
                        </div>
                    @else
                        <div class="timeline">
                            @foreach($auditLogs as $log)
                                <div class="timeline-item mb-4">
                                    <div class="d-flex align-items-start">
                                        <div class="timeline-icon me-3">
                                            @if($log->event === 'created')
                                                <span class="badge bg-light-success text-success"><i class="fas fa-plus"></i></span>
                                            @elseif($log->event === 'updated')
                                                <span class="badge bg-light-info text-info"><i class="fas fa-edit"></i></span>
                                            @elseif($log->event === 'deleted')
                                                <span class="badge bg-light-danger text-danger"><i class="fas fa-trash"></i></span>
                                            @endif
                                        </div>
                                        <div class="timeline-content flex-grow-1">
                                            <div class="d-flex justify-content-between">
                                                <strong>{{ ucfirst($log->event) }}</strong>
                                                <small class="text-muted">{{ $log->created_at->format('M d, Y h:i A') }}</small>
                                            </div>
                                            <div class="text-muted small">
                                                By: {{ $log->user?->name ?? 'System' }}
                                            </div>
                                            @if($log->event === 'updated' && count($log->changed_fields) > 0)
                                                <div class="mt-2">
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Field</th>
                                                                <th>Old Value</th>
                                                                <th>New Value</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($log->changed_fields as $field => $values)
                                                                @if(!in_array($field, ['updated_at', 'created_at']))
                                                                    <tr>
                                                                        <td><code>{{ $field }}</code></td>
                                                                        <td class="text-danger">{{ is_array($values['old']) ? json_encode($values['old']) : $values['old'] ?? '-' }}</td>
                                                                        <td class="text-success">{{ is_array($values['new']) ? json_encode($values['new']) : $values['new'] ?? '-' }}</td>
                                                                    </tr>
                                                                @endif
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endif
                                            <div class="mt-1">
                                                <small class="text-muted">IP: {{ $log->ip_address }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
