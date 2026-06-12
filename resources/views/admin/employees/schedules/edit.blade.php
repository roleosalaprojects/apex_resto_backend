@extends('layout.app')
@section('header')
    - Edit Schedule
@endsection
@section('title')
    Edit Schedule - {{ $employee->name }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('schedules.index') }}">Schedules</a></li>
    <li class="breadcrumb-item text-muted">Edit Schedule</li>
@endsection
@section('content')
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Edit Schedule for {{ $employee->name }}</h4>
                </div>
                <form action="{{ route('schedules.update', $employee) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        @include('admin.employees.schedules._form')
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('schedules.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Schedule Info</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Set the start time for each working day. Check "Rest Day" for days off.
                    </p>
                    <p class="text-muted mb-3">
                        <strong>Grace Period:</strong> {{ config('attendance.grace_period', 15) }} minutes
                    </p>
                    <p class="text-muted mb-0">
                        Employees arriving after the scheduled start time plus grace period will be marked as late.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
<script>
$(function() {
    // Toggle time input when rest day checkbox changes
    $('.rest-day-checkbox').on('change', function() {
        var $row = $(this).closest('.schedule-row');
        var $timeInput = $row.find('.start-time-input');

        if ($(this).is(':checked')) {
            $timeInput.prop('disabled', true).val('');
        } else {
            $timeInput.prop('disabled', false);
        }
    });

    // Initialize disabled state for rest days
    $('.rest-day-checkbox:checked').each(function() {
        var $row = $(this).closest('.schedule-row');
        $row.find('.start-time-input').prop('disabled', true);
    });
});
</script>
@endsection
