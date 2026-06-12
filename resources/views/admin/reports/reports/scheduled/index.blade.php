@extends('layout.app')
@section('header')
    - Scheduled Reports
@endsection
@section('title')
    Scheduled Reports
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted"><span class="">Reports</span></li>
    <li class="breadcrumb-item text-muted">Scheduled Reports</li>
@endsection
@section('content')
    {{-- Add Recipient Form --}}
    <div class="card card-bordered mb-7">
        <div class="card-header">
            <h3 class="card-title">Add Report Recipient</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('reports.scheduled.recipients.store') }}" method="POST" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Email Address</label>
                    <input type="email" name="email" class="form-control form-control-solid" placeholder="email@example.com" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Report Type</label>
                    <select name="report_type" class="form-select form-select-solid">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="both">Both</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Add Recipient</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Recipients Table --}}
    <div class="card card-flush">
        <div class="card-header">
            <h3 class="card-title">Report Recipients</h3>
        </div>
        <div class="card-body">
            @if($recipients->isEmpty())
                <div class="text-center text-muted py-10">
                    <p class="fs-5">No recipients configured.</p>
                    <p>Add email recipients above to receive automated daily and weekly sales reports.</p>
                </div>
            @else
                <table class="table table-row-bordered table-row-gray-300 align-middle gs-0 gy-3">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th>Email</th>
                            <th>Report Type</th>
                            <th>Status</th>
                            <th>Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recipients as $recipient)
                        <tr>
                            <td>{{ $recipient->email }}</td>
                            <td>
                                <span class="badge badge-light-primary">{{ ucfirst($recipient->report_type) }}</span>
                            </td>
                            <td>
                                @if($recipient->is_active)
                                    <span class="badge badge-light-success">Active</span>
                                @else
                                    <span class="badge badge-light-danger">Inactive</span>
                                @endif
                            </td>
                            <td>{{ $recipient->created_at->format('M d, Y') }}</td>
                            <td>
                                <form action="{{ route('reports.scheduled.recipients.destroy', $recipient) }}" method="POST" onsubmit="return confirm('Remove this recipient?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Schedule Info --}}
    <div class="card card-bordered mt-7">
        <div class="card-body">
            <h5 class="fw-bold mb-3">Schedule Information</h5>
            <ul class="text-gray-600">
                <li><strong>Daily Reports:</strong> Sent every day at 8:00 AM</li>
                <li><strong>Weekly Reports:</strong> Sent every Monday at 8:00 AM</li>
            </ul>
        </div>
    </div>
@endsection
