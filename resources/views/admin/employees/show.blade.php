@extends('layout.app')
@section('header')
    - Employee Record
@endsection
@section('title')
    {{$employee->user->name}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item "><a class="" href="{{route('employees.index')}}">Employee List</a></li>
    <li class="breadcrumb-item text-muted">{{$employee->user->name}}</li>
@endsection
@section('actions')

@endsection
@section('content')
<div class="d-flex flex-column flex-xl-row">
    {{-- beginL::Sidebar --}}
    <div class="flex-column flex-lg-row-auto w-100 w-xl-350px mb-10">
        <!--begin::Card-->
        <div class="card mb-5 mb-xl-8">
            <!--begin::Card body-->
            <div class="card-body pt-15">
                <!--begin::Summary-->
                <div class="d-flex flex-center flex-column mb-5">
                    <!--begin::Avatar-->
                    <div class="symbol symbol-100px symbol-circle mb-7">
                        <img src="{{ ($employee->image) ? asset($employee->image) : asset('assets/media/avatars/blank.png') }}" class="profile-user-img img-fluid img-circle" alt="Profile Picture">
                    </div>
                    <!--end::Avatar-->
                    <!--begin::Name-->
                    <a href="#" class="fs-3 text-gray-800 text-hover-primary fw-bold mb-1">{{ $employee->user->name }}</a>
                    <!--end::Name-->
                    <!--begin::Position-->
                    <div class="fs-5 fw-semibold text-muted mb-6">{{ $employee->user->role->name }}</div>
                    <!--end::Position-->
                    <!--begin::Info-->
                    <div class="d-flex flex-wrap flex-center">
                        <!--begin::Stats-->
                        <div class="border border-gray-300 border-dashed rounded py-3 px-3 mb-3">
                            <div class="fs-4 fw-bold text-gray-700">
                                <span class="w-75px">₱ {{ number_format($overallSales, 2) }}</span>
                            </div>
                            <div class="fw-semibold text-muted">Sales</div>
                        </div>
                        <!--end::Stats-->
                        <!--begin::Stats-->
                        <div class="border border-gray-300 border-dashed rounded py-3 px-3 mx-4 mb-3">
                            <div class="fs-4 fw-bold text-gray-700">
                                <span class="w-50px">₱ {{ number_format($overallRefunds, 2) }}</span>
                            </div>
                            <div class="fw-semibold text-muted">Refunds</div>
                        </div>
                        <!--end::Stats-->
                        <!--begin::Stats-->
                        <div class="border border-gray-300 border-dashed rounded py-3 px-3 mb-3">
                            <div class="fs-4 fw-bold text-gray-700">
                                <span class="w-50px">₱ {{ number_format($overallSales - $overallRefunds, 2) }}</span>
                            </div>
                            <div class="fw-semibold text-muted">Net</div>
                        </div>
                        <!--end::Stats-->
                    </div>
                    <!--end::Info-->
                </div>
                <!--end::Summary-->
                <!--begin::Details toggle-->
                <div class="d-flex flex-stack fs-4 py-3">
                    <div class="fw-bold rotate collapsible" data-bs-toggle="collapse" href="#kt_customer_view_details" role="button" aria-expanded="false" aria-controls="kt_customer_view_details">Details
                        <span class="ms-2 rotate-180">
                            <span class="svg-icon svg-icon-3">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="currentColor"></path>
                                </svg>
                            </span>
                        </span>
                    </div>
                </div>
                <!--end::Details toggle-->
                <div class="separator separator-dashed my-3"></div>
                <!--begin::Details content-->
                <div id="kt_customer_view_details" class="collapse show">
                    <div class="py-5 fs-6">
                        <div class="fw-bold mt-5">Location</div>
                        <div class="text-gray-600">{{ $employee->address }}</div>
                        <div class="fw-bold mt-5">Phone</div>
                        <div class="text-gray-600">
                            <a href="#" class="text-gray-600 text-hover-primary">{{ $employee->phone }}</a>
                        </div>
                        <div class="fw-bold mt-5">Email Address</div>
                        <div class="text-gray-600">{{$employee->user->email}}</div>
                    </div>
                </div>
                <!--end::Details content-->
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
    </div>
    {{-- end::Sidebar --}}
    {{-- begin::Content --}}
    <div class="flex-lg-row-fluid ms-lg-15">
        <div class="card card-flush">
            <!--begin::Card header with tabs-->
            <div class="card-header">
                <ul class="nav nav-tabs nav-line-tabs nav-stretch fs-6 border-0 fw-bold" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tab_activity_log" role="tab">
                            <i class="ki-outline ki-time fs-4 me-1"></i> Activity Log
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab_attendance" role="tab" id="attendanceTabLink">
                            <i class="ki-outline ki-calendar fs-4 me-1"></i> Attendance
                        </a>
                    </li>
                </ul>
            </div>
            <!--end::Card header-->
            <!--begin::Card body-->
            <div class="card-body">
                <div class="tab-content">
                    <!--begin::Activity Log Tab-->
                    <div class="tab-pane fade show active" id="tab_activity_log" role="tabpanel">
                        <!--begin::Toolbar-->
                        <div class="d-flex align-items-center gap-3 mb-6">
                            <div class="input-group w-auto">
                                <input type="text" class="form-control" id="activityDateRange" placeholder="Select date range" readonly>
                                <span class="input-group-text"><i class="ki-outline ki-calendar fs-4"></i></span>
                            </div>
                            <button type="button" class="btn btn-light-primary btn-sm" id="btnExportCsv">
                                <i class="ki-outline ki-exit-up fs-5 me-1"></i> Export CSV
                            </button>
                        </div>
                        <!--end::Toolbar-->
                        <!--begin::Timeline container (scrollable, matches calendar height)-->
                        <div id="activityLogContainer" style="max-height: 600px; overflow-y: auto;">
                            <div class="d-flex justify-content-center py-10">
                                <span class="spinner-border text-primary" role="status"></span>
                            </div>
                        </div>
                        <!--end::Timeline container-->
                    </div>
                    <!--end::Activity Log Tab-->
                    <!--begin::Attendance Tab-->
                    <div class="tab-pane fade" id="tab_attendance" role="tabpanel">
                        <div class="mb-3">
                            <span class="badge badge-light-success me-2">Present</span>
                            <span class="badge badge-light-warning me-2">Late</span>
                            <span class="badge badge-light-danger">Absent</span>
                        </div>
                        <div id="employeeAttendanceCalendar"></div>
                    </div>
                    <!--end::Attendance Tab-->
                </div>
            </div>
            <!--end::Card body-->
        </div>
    </div>
    {{-- end::Content --}}
</div>
@endsection
@section('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.css') }}">
@endsection
@section('vendor-scripts')
    <script src="{{ asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.js') }}"></script>
@endsection
@section('scripts')
<script>
$(function() {
    var employeeId = {{ $employee->user->id }};
    var timelineUrl = "{{ route('employee.timeline') }}";
    var calendarEventsUrl = "{{ route('attendance.calendar-events') }}";
    var startDate = moment().startOf("day");
    var endDate = moment().endOf("day");
    var attendanceCalendar = null;
    var attendanceTabInitialized = false;
    var loadedGroups = [];

    // Initialize DateRangePicker
    $("#activityDateRange").daterangepicker({
        startDate: startDate,
        endDate: endDate,
        locale: { format: "MMM D, YYYY" },
        ranges: {
            "Today": [moment(), moment()],
            "Yesterday": [moment().subtract(1, "days"), moment().subtract(1, "days")],
            "Last 7 Days": [moment().subtract(6, "days"), moment()],
            "Last 30 Days": [moment().subtract(29, "days"), moment()],
            "This Month": [moment().startOf("month"), moment().endOf("month")],
            "Last Month": [moment().subtract(1, "month").startOf("month"), moment().subtract(1, "month").endOf("month")]
        }
    }, function(start, end) {
        startDate = start;
        endDate = end;
        loadActivityLog();
    });

    // Load activity log on page load
    loadActivityLog();

    function loadActivityLog() {
        var container = $("#activityLogContainer");
        container.html('<div class="d-flex justify-content-center py-10"><span class="spinner-border text-primary" role="status"></span></div>');

        $.ajax({
            url: timelineUrl,
            data: {
                startDate: startDate.format("YYYY-MM-DD"),
                endDate: endDate.format("YYYY-MM-DD"),
                id: employeeId
            },
            success: function(groups) {
                loadedGroups = groups;
                renderActivityLog(groups);
            },
            error: function() {
                container.html('<div class="text-center text-muted py-10"><i class="ki-outline ki-information fs-2x mb-3 d-block"></i>Failed to load activity log.</div>');
            }
        });
    }

    function renderActivityLog(groups) {
        var container = $("#activityLogContainer");

        if (!groups || groups.length === 0) {
            container.html('<div class="text-center text-muted py-10"><i class="ki-outline ki-calendar fs-2x mb-3 d-block"></i>No activity found for this period.</div>');
            return;
        }

        var html = '<div class="timeline timeline-border-dashed">';

        groups.forEach(function(group) {
            // Date group header
            html += '<div class="timeline-item">';
            html += '<div class="timeline-line"></div>';
            html += '<div class="timeline-icon"><i class="ki-outline ki-calendar fs-2 text-primary"></i></div>';
            html += '<div class="timeline-content mb-6 mt-n1">';
            html += '<div class="fs-5 fw-bold text-primary">' + group.date_group + '</div>';
            html += '</div>';
            html += '</div>';

            // Entries
            group.entries.forEach(function(entry) {
                html += '<div class="timeline-item">';
                html += '<div class="timeline-line"></div>';
                html += '<div class="timeline-icon"><i class="' + entry.type_icon + ' fs-2 text-' + entry.type_color + '"></i></div>';
                html += '<div class="timeline-content mb-10 mt-n1">';

                // Header: badge + time
                html += '<div class="d-flex align-items-center gap-2 mb-2">';
                html += '<span class="badge badge-light-' + entry.type_color + '">' + entry.type_label + '</span>';
                html += '<span class="text-muted fs-7">' + entry.time + '</span>';
                html += '</div>';

                // Reason
                if (entry.reason) {
                    html += '<div class="fs-6 text-gray-700 mb-2">' + entry.reason + '</div>';
                }

                // Detail box for financial entries
                var hasDetails = entry.cash_in || entry.cash_out || entry.rendered || entry.sale_id;
                if (hasDetails) {
                    html += '<div class="border border-dashed border-gray-300 rounded p-3">';
                    html += '<div class="d-flex flex-wrap gap-4 fs-7">';

                    if (entry.cash_in) {
                        html += '<div><span class="text-muted">Cash In:</span> <span class="fw-bold text-success">&#8369; ' + entry.cash_in + '</span></div>';
                    }
                    if (entry.cash_out) {
                        html += '<div><span class="text-muted">Cash Out:</span> <span class="fw-bold text-danger">&#8369; ' + entry.cash_out + '</span></div>';
                    }
                    if (entry.rendered) {
                        html += '<div><span class="text-muted">Rendered:</span> <span class="fw-bold">&#8369; ' + entry.rendered + '</span></div>';
                    }
                    if (entry.sale_id) {
                        html += '<div><span class="text-muted">Invoice:</span> <a href="/admin/reports/receipts/receipts/' + entry.sale_id + '" target="_blank" class="fw-bold text-primary">' + (entry.sale_son || '#' + entry.sale_id) + '</a>';
                        if (entry.sale_total) {
                            html += ' <span class="text-muted">-</span> <span class="fw-bold">&#8369; ' + entry.sale_total + '</span>';
                        }
                        html += '</div>';
                    }

                    // Store & Terminal info
                    if (entry.store_name || entry.pos_name) {
                        html += '<div class="text-muted">';
                        if (entry.store_name) {
                            html += '<i class="ki-outline ki-shop fs-7 me-1"></i>' + entry.store_name;
                        }
                        if (entry.pos_name) {
                            html += (entry.store_name ? ' &middot; ' : '') + '<i class="ki-outline ki-devices fs-7 me-1"></i>' + entry.pos_name;
                        }
                        html += '</div>';
                    }

                    html += '</div>';
                    html += '</div>';
                }

                html += '</div>';
                html += '</div>';
            });
        });

        html += '</div>';
        container.html(html);
    }

    // Export CSV
    $('#btnExportCsv').on('click', function() {
        if (!loadedGroups || loadedGroups.length === 0) {
            Swal.fire({ icon: 'info', text: 'No activity data to export.', timer: 2000, showConfirmButton: false });
            return;
        }

        var rows = [['Date', 'Time', 'Type', 'Reason', 'Cash In', 'Cash Out', 'Rendered', 'Invoice', 'Sale Total', 'Store', 'Terminal']];
        loadedGroups.forEach(function(group) {
            group.entries.forEach(function(e) {
                rows.push([
                    group.date_group,
                    e.time,
                    e.type_label,
                    (e.reason || '').replace(/"/g, '""'),
                    e.cash_in || '',
                    e.cash_out || '',
                    e.rendered || '',
                    e.sale_son || '',
                    e.sale_total || '',
                    e.store_name || '',
                    e.pos_name || ''
                ]);
            });
        });

        var csv = rows.map(function(r) {
            return r.map(function(cell) { return '"' + cell + '"'; }).join(',');
        }).join('\n');

        var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = '{{ $employee->user->name }} - Activity Log (' + startDate.format('YYYY-MM-DD') + ' to ' + endDate.format('YYYY-MM-DD') + ').csv';
        link.click();
    });

    // Attendance Calendar — lazy init on tab shown
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        if (e.target.id === 'attendanceTabLink' && !attendanceTabInitialized) {
            attendanceTabInitialized = true;
            initAttendanceCalendar();
        }
    });

    function initAttendanceCalendar() {
        var calendarEl = document.getElementById('employeeAttendanceCalendar');
        attendanceCalendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
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
                    return { user_id: employeeId };
                }
            }],
            eventClick: function(info) {
                showEventDetails(info.event);
            }
        });
        attendanceCalendar.render();
    }

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
<script type="text/javascript">
    $.ajaxSetup({ headers: { 'csrftoken' : '{{ csrf_token() }}' } });
</script>
@endsection
