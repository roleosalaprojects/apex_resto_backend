@extends('layout.app')
@section('header')
    - Audit Log Details
@endsection
@section('title')
    Audit Log Details
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('audit_logs.index') }}">Audit Trail</a></li>
    <li class="breadcrumb-item text-muted">Log #{{ $auditLog->id }}</li>
@endsection
@section('content')
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Log Information</h4>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th>Date/Time</th>
                            <td>{{ $auditLog->created_at->format('M d, Y h:i:s A') }}</td>
                        </tr>
                        <tr>
                            <th>User</th>
                            <td>{{ $auditLog->user?->name ?? 'System' }}</td>
                        </tr>
                        <tr>
                            <th>Event</th>
                            <td>
                                @switch($auditLog->event)
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
                                        <span class="badge bg-light text-dark">{{ ucfirst($auditLog->event) }}</span>
                                @endswitch
                            </td>
                        </tr>
                        <tr>
                            <th>Model Type</th>
                            <td><code>{{ $auditLog->auditable_type }}</code></td>
                        </tr>
                        <tr>
                            <th>Record ID</th>
                            <td>{{ $auditLog->auditable_id }}</td>
                        </tr>
                        <tr>
                            <th>IP Address</th>
                            <td>{{ $auditLog->ip_address ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>URL</th>
                            <td><small>{{ $auditLog->url ?? '-' }}</small></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Changes</h4>
                </div>
                <div class="card-body">
                    @php
                        $changes = $auditLog->changed_fields;
                    @endphp
                    @if(count($changes) > 0)
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Old Value</th>
                                    <th>New Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($changes as $field => $change)
                                    <tr>
                                        <td><strong>{{ $field }}</strong></td>
                                        <td>
                                            @if(is_array($change['old']))
                                                <pre class="mb-0"><code>{{ json_encode($change['old'], JSON_PRETTY_PRINT) }}</code></pre>
                                            @else
                                                {{ $change['old'] ?? '-' }}
                                            @endif
                                        </td>
                                        <td>
                                            @if(is_array($change['new']))
                                                <pre class="mb-0"><code>{{ json_encode($change['new'], JSON_PRETTY_PRINT) }}</code></pre>
                                            @else
                                                {{ $change['new'] ?? '-' }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="row">
                            @if($auditLog->old_values)
                                <div class="col-md-6">
                                    <h6>Old Values</h6>
                                    <pre class="bg-light p-3"><code>{{ json_encode($auditLog->old_values, JSON_PRETTY_PRINT) }}</code></pre>
                                </div>
                            @endif
                            @if($auditLog->new_values)
                                <div class="col-md-6">
                                    <h6>New Values</h6>
                                    <pre class="bg-light p-3"><code>{{ json_encode($auditLog->new_values, JSON_PRETTY_PRINT) }}</code></pre>
                                </div>
                            @endif
                            @if(!$auditLog->old_values && !$auditLog->new_values)
                                <div class="col-12">
                                    <p class="text-muted mb-0">No data changes recorded.</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="row mt-3">
        <div class="col">
            <a href="{{ route('audit_logs.index') }}" class="btn btn-secondary">Back to Audit Trail</a>
        </div>
    </div>
@endsection
