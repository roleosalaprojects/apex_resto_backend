@extends('layout.app')
@section('header')
    - Shift Management
@endsection
@section('title')
    Shift Management
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">Shifts</li>
@endsection
@section('actions')
    <a href="{{ route('shifts.create') }}" class="btn btn-primary">Clock In</a>
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
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Your Shifts</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Clock In</th>
                                    <th>Clock Out</th>
                                    <th>Store</th>
                                    <th>Starting Cash</th>
                                    <th>Ending Cash</th>
                                    <th>Difference</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($shifts as $shift)
                                    <tr>
                                        <td>{{ $shift->clock_in?->format('M d, Y') ?? '-' }}</td>
                                        <td>{{ $shift->clock_in?->format('h:i A') ?? '-' }}</td>
                                        <td>{{ $shift->clock_out?->format('h:i A') ?? '-' }}</td>
                                        <td>{{ $shift->store?->name ?? '-' }}</td>
                                        <td>{{ number_format($shift->starting_cash, 2) }}</td>
                                        <td>{{ $shift->ending_cash ? number_format($shift->ending_cash, 2) : '-' }}</td>
                                        <td>
                                            @if($shift->cash_difference !== null)
                                                <span class="{{ $shift->cash_difference >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ number_format($shift->cash_difference, 2) }}
                                                </span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($shift->status === 'active')
                                                <span class="badge bg-success">Active</span>
                                            @elseif($shift->status === 'completed')
                                                <span class="badge bg-secondary">Completed</span>
                                            @else
                                                <span class="badge bg-danger">Cancelled</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('shifts.show', $shift) }}" class="btn btn-sm btn-info">View</a>
                                            @if($shift->status === 'active')
                                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#clockOutModal{{ $shift->id }}">
                                                    Clock Out
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                    @if($shift->status === 'active')
                                        <div class="modal fade" id="clockOutModal{{ $shift->id }}" tabindex="-1">
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
                                                                <label class="form-label">Ending Cash</label>
                                                                <input type="number" step="0.01" name="ending_cash" class="form-control" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Notes</label>
                                                                <textarea name="notes" class="form-control" rows="3"></textarea>
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
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center">No shifts found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $shifts->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
