@extends('layout.app')
@section('header')
    - Attendance Summary
@endsection
@section('title')
    Attendance Summary
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('attendance.index') }}">Attendance</a></li>
    <li class="breadcrumb-item text-muted">Summary</li>
@endsection
@section('actions')
    <a href="{{ route('attendance.index') }}" class="btn btn-light-primary">
        <i class="fas fa-list me-1"></i> View Records
    </a>
@endsection
@section('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.css') }}">
@endsection
@section('vendor-scripts')
    <script src="{{ asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.js') }}"></script>
@endsection
@section('content')
    <div class="row">
        <div class="col">
            {{-- Filter Card --}}
            <div class="card mb-5">
                <div class="card-header">
                    <h4 class="card-title">Filter Period</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('attendance.summary') }}" method="GET" class="row g-3" id="summaryForm">
                        <div class="col-md-4">
                            <label class="form-label">Date Range</label>
                            <input class="form-control form-control-solid" placeholder="Pick date range" id="daterangepicker"/>
                            <input type="hidden" name="start_date" id="start_date" value="{{ $startDate }}">
                            <input type="hidden" name="end_date" id="end_date" value="{{ $endDate }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Store</label>
                            <select name="store_id" class="form-select" id="storeFilter">
                                <option value="">All Stores</option>
                                @foreach($stores as $store)
                                    <option value="{{ $store->id }}" {{ $storeId == $store->id ? 'selected' : '' }}>
                                        {{ $store->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> Generate
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Global Attendance Calendar --}}
            <div class="card mb-5">
                <div class="card-header">
                    <h4 class="card-title">Attendance Calendar</h4>
                    <div class="card-toolbar">
                        <span class="badge badge-light-success me-2">Present</span>
                        <span class="badge badge-light-warning me-2">Late</span>
                        <span class="badge badge-light-danger">Absent</span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="attendanceCalendar"></div>
                </div>
            </div>

            {{-- Summary Table --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        Employee Attendance Summary
                        <small class="text-muted" id="dateRangeLabel">({{ \Carbon\Carbon::parse($startDate)->format('M d') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }})</small>
                    </h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th class="text-center">Days Present</th>
                                    <th class="text-center">Days Absent</th>
                                    <th class="text-center">Late Days</th>
                                    <th class="text-center">Late Minutes</th>
                                    <th class="text-center">Total Hours</th>
                                    <th class="text-center">Avg Hours/Day</th>
                                    <th class="text-center">Calendar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($employees as $employee)
                                    @php
                                        $records = $employee->attendanceRecords;
                                        $presentDays = $records->where('status', 'present')->count();
                                        $absentDays = $records->where('status', 'absent')->count();
                                        $lateDays = $records->where('is_late', true)->count();
                                        $totalLateMinutes = $records->sum('late_minutes');
                                        $totalHours = $records->sum('hours_rendered');
                                        $avgHours = $presentDays > 0 ? $totalHours / $presentDays : 0;
                                    @endphp
                                    <tr>
                                        <td>
                                            <a href="{{ route('attendance.index', ['user_id' => $employee->id, 'date_from' => $startDate, 'date_to' => $endDate]) }}">
                                                {{ $employee->name }}
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-success">{{ $presentDays }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-danger">{{ $absentDays }}</span>
                                        </td>
                                        <td class="text-center">
                                            @if($lateDays > 0)
                                                <span class="badge badge-light-warning">{{ $lateDays }}</span>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($totalLateMinutes > 0)
                                                <span class="text-warning fw-bold">{{ $totalLateMinutes }}</span>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ number_format($totalHours, 2) }}</td>
                                        <td class="text-center">{{ number_format($avgHours, 2) }}</td>
                                        <td class="text-center">
                                            <button type="button"
                                                class="btn btn-sm btn-icon btn-light-primary btn-employee-calendar"
                                                data-employee-id="{{ $employee->id }}"
                                                data-employee-name="{{ $employee->name }}"
                                                title="View Calendar">
                                                <i class="fas fa-calendar-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-5">No employees found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($employees->isNotEmpty())
                                <tfoot class="table-light">
                                    @php
                                        $totalPresent = $employees->sum(fn($e) => $e->attendanceRecords->where('status', 'present')->count());
                                        $totalAbsent = $employees->sum(fn($e) => $e->attendanceRecords->where('status', 'absent')->count());
                                        $totalLateDays = $employees->sum(fn($e) => $e->attendanceRecords->where('is_late', true)->count());
                                        $grandTotalLateMinutes = $employees->sum(fn($e) => $e->attendanceRecords->sum('late_minutes'));
                                        $grandTotalHours = $employees->sum(fn($e) => $e->attendanceRecords->sum('hours_rendered'));
                                    @endphp
                                    <tr>
                                        <th>Totals</th>
                                        <th class="text-center">{{ $totalPresent }}</th>
                                        <th class="text-center">{{ $totalAbsent }}</th>
                                        <th class="text-center">{{ $totalLateDays }}</th>
                                        <th class="text-center">{{ $grandTotalLateMinutes }}</th>
                                        <th class="text-center">{{ number_format($grandTotalHours, 2) }}</th>
                                        <th class="text-center">-</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Per-Employee Calendar Modal --}}
    <div class="modal fade" id="employeeCalendarModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <span id="employeeCalendarTitle">Employee Calendar</span>
                    </h5>
                    <div class="ms-auto me-5">
                        <span class="badge badge-light-success me-2">Present</span>
                        <span class="badge badge-light-warning me-2">Late</span>
                        <span class="badge badge-light-danger">Absent</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="employeeCalendar"></div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
<script>
$(function() {
    var startDate = '{{ $startDate }}';
    var endDate = '{{ $endDate }}';
    var storeId = '{{ $storeId }}';
    var calendarEventsUrl = '{{ route('attendance.calendar-events') }}';

    // Initialize daterangepicker
    $('#daterangepicker').daterangepicker({
        startDate: moment(startDate),
        endDate: moment(endDate),
        showDropdowns: true,
        ranges: {
            "Today": [moment(), moment()],
            "Yesterday": [moment().subtract(1, "days"), moment().subtract(1, "days")],
            "Last 7 Days": [moment().subtract(6, "days"), moment()],
            "Last 30 Days": [moment().subtract(29, "days"), moment()],
            "This Month": [moment().startOf("month"), moment().endOf("month")],
            "Last Month": [moment().subtract(1, "month").startOf("month"), moment().subtract(1, "month").endOf("month")],
            "This Year": [moment().startOf("year"), moment().endOf("year")],
        }
    }, function(start, end, label) {
        $('#start_date').val(start.format("YYYY-MM-DD"));
        $('#end_date').val(end.format("YYYY-MM-DD"));
    });

    // Global Attendance Calendar
    var calendarEl = document.getElementById('attendanceCalendar');
    var globalCalendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        initialDate: startDate,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth'
        },
        height: 'auto',
        dayMaxEvents: 3,
        eventSources: [{
            url: calendarEventsUrl,
            extraParams: function() {
                return {
                    store_id: storeId
                };
            }
        }],
        eventClick: function(info) {
            showEventDetails(info.event);
        }
    });
    globalCalendar.render();

    // Per-Employee Calendar Modal
    var employeeCalendar = null;
    var employeeCalendarModal = document.getElementById('employeeCalendarModal');

    $(document).on('click', '.btn-employee-calendar', function() {
        var employeeId = $(this).data('employee-id');
        var employeeName = $(this).data('employee-name');
        $('#employeeCalendarTitle').text(employeeName + ' - Attendance Calendar');
        $('#employeeCalendarModal').data('employee-id', employeeId);
        var modal = new bootstrap.Modal(employeeCalendarModal);
        modal.show();
    });

    employeeCalendarModal.addEventListener('shown.bs.modal', function() {
        var employeeId = $('#employeeCalendarModal').data('employee-id');
        var empCalEl = document.getElementById('employeeCalendar');

        if (employeeCalendar) {
            employeeCalendar.destroy();
        }

        employeeCalendar = new FullCalendar.Calendar(empCalEl, {
            initialView: 'dayGridMonth',
            initialDate: startDate,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth'
            },
            height: 'auto',
            dayMaxEvents: 3,
            eventSources: [{
                url: calendarEventsUrl,
                extraParams: function() {
                    return {
                        store_id: storeId,
                        user_id: employeeId
                    };
                }
            }],
            eventClick: function(info) {
                showEventDetails(info.event);
            }
        });
        employeeCalendar.render();
    });

    // Event Click — SweetAlert Details
    function showEventDetails(event) {
        var props = event.extendedProps;
        var statusBadge = '';
        if (props.status === 'present' && props.is_late) {
            statusBadge = '<span class="badge badge-light-warning">Late</span>';
        } else if (props.status === 'present') {
            statusBadge = '<span class="badge badge-light-success">Present</span>';
        } else {
            statusBadge = '<span class="badge badge-light-danger">Absent</span>';
        }

        var lateInfo = '';
        if (props.is_late && props.late_minutes > 0) {
            lateInfo = '<div class="mb-2"><strong>Late:</strong> ' + props.late_minutes + ' minutes</div>';
        }

        var remarksInfo = '';
        if (props.remarks) {
            remarksInfo = '<div class="mb-2"><strong>Remarks:</strong> ' + props.remarks + '</div>';
        }

        Swal.fire({
            html: '<div class="text-start">' +
                '<div class="mb-3 fs-5 fw-bold">' + (props.employee || 'Unknown') + '</div>' +
                '<div class="mb-2"><strong>Date:</strong> ' + moment(event.start).format('MMM D, YYYY') + '</div>' +
                '<div class="mb-2"><strong>Status:</strong> ' + statusBadge + '</div>' +
                '<div class="mb-2"><strong>Store:</strong> ' + (props.store || '-') + '</div>' +
                '<div class="mb-2"><strong>Time In:</strong> ' + (props.time_in || '-') + '</div>' +
                '<div class="mb-2"><strong>Time Out:</strong> ' + (props.time_out || '-') + '</div>' +
                '<div class="mb-2"><strong>Hours:</strong> ' + props.hours + '</div>' +
                lateInfo +
                remarksInfo +
            '</div>',
            showCloseButton: true,
            showConfirmButton: false,
            width: '400px'
        });
    }
});
</script>
@endsection
