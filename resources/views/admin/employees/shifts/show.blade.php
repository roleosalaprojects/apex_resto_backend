@extends('layout.app')
@section('header')
    - Shift Details
@endsection
@section('title')
    Shift Details
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('shifts.index') }}">Shifts</a></li>
    <li class="breadcrumb-item text-muted">Shift #{{ $shift->id }}</li>
@endsection
@section('content')
    @if(session('msg'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('msg') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Shift Information</h4>
                    @if($shift->status === 'active')
                        <span class="badge bg-success">Active</span>
                    @elseif($shift->status === 'completed')
                        <span class="badge bg-secondary">Completed</span>
                    @else
                        <span class="badge bg-danger">Cancelled</span>
                    @endif
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th>Employee</th>
                            <td>{{ $shift->user?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Store</th>
                            <td>{{ $shift->store?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Terminal</th>
                            <td>{{ $shift->pos?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Clock In</th>
                            <td>{{ $shift->clock_in?->format('M d, Y h:i A') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Clock Out</th>
                            <td>{{ $shift->clock_out?->format('M d, Y h:i A') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Starting Cash</th>
                            <td>{{ number_format($shift->starting_cash, 2) }}</td>
                        </tr>
                        <tr>
                            <th>Ending Cash</th>
                            <td>{{ $shift->ending_cash !== null ? number_format($shift->ending_cash, 2) : '-' }}</td>
                        </tr>
                        <tr>
                            <th>Cash Difference</th>
                            <td>
                                @if($shift->cash_difference !== null)
                                    <span class="{{ $shift->cash_difference >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($shift->cash_difference, 2) }}
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        @if($shift->worked_minutes !== null)
                        <tr>
                            <th>Worked Hours</th>
                            <td>{{ floor($shift->worked_minutes / 60) }}h {{ $shift->worked_minutes % 60 }}m</td>
                        </tr>
                        @endif
                        @if($shift->notes)
                        <tr>
                            <th>Notes</th>
                            <td>{{ $shift->notes }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
                @if($shift->status === 'active')
                <div class="card-footer">
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#clockOutModal">
                        Clock Out
                    </button>
                </div>
                @endif
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Breaks</h4>
                    @if($shift->status === 'active')
                        @php
                            $activeBreak = $shift->breaks->where('break_end', null)->first();
                        @endphp
                        @if($activeBreak)
                            <form action="{{ route('shifts.end-break', $shift) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success">End Break</button>
                            </form>
                        @else
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#breakModal">
                                Start Break
                            </button>
                        @endif
                    @endif
                </div>
                <div class="card-body">
                    @if($shift->breaks->count() > 0)
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($shift->breaks as $break)
                                    <tr>
                                        <td>
                                            <span class="badge bg-info">{{ ucfirst($break->type) }}</span>
                                        </td>
                                        <td>{{ $break->break_start->format('h:i A') }}</td>
                                        <td>{{ $break->break_end?->format('h:i A') ?? 'Ongoing' }}</td>
                                        <td>{{ $break->duration_minutes !== null ? $break->duration_minutes . ' min' : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3">Total Break Time</th>
                                    <td><strong>{{ $shift->total_break_minutes }} min</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    @else
                        <p class="text-muted mb-0">No breaks recorded.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($shift->status === 'active')
    <div class="modal fade" id="clockOutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('shifts.clock-out', $shift) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Clock Out</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Ending Cash <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="ending_cash" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3">{{ $shift->notes }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Clock Out</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="breakModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('shifts.start-break', $shift) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Start Break</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Break Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="short">Short Break</option>
                                <option value="lunch">Lunch Break</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Start Break</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
@endsection
