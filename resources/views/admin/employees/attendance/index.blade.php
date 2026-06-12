@extends('layout.app')
@section('header')
    - Attendance Records
@endsection
@section('title')
    Attendance Records
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">Attendance</li>
@endsection
@section('actions')
    <x-data-table.actions :show-export="false">
        <div class="px-5 py-3">
            <div class="fs-5 text-dark fw-bold">Export Options</div>
        </div>
        <div class="menu-item px-3">
            <a href="#" class="menu-link px-3" id="exportCsv">Export as CSV</a>
        </div>
    </x-data-table.actions>
    <x-general.search-table title="Attendance"></x-general.search-table>
    <a href="{{ route('attendance.summary') }}" class="btn btn-light-primary me-2">
        <i class="fas fa-chart-bar me-1"></i> Summary
    </a>
    @if($access->attndnc_create)
        <a href="{{ route('attendance.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Add Record
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
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card mb-6">
        <div class="card-body py-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fs-7 fw-semibold">Store</label>
                    <select class="form-select form-select-sm" id="filterStore">
                        <option value="">All Stores</option>
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-7 fw-semibold">Employee</label>
                    <select class="form-select form-select-sm" id="filterEmployee">
                        <option value="">All Employees</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-7 fw-semibold">Status</label>
                    <select class="form-select form-select-sm" id="filterStatus">
                        <option value="">All</option>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-7 fw-semibold">Date Range</label>
                    <input class="form-control form-control-sm form-control-solid" placeholder="Pick date range" id="daterangepicker" readonly>
                    <input type="hidden" id="filterDateFrom">
                    <input type="hidden" id="filterDateTo">
                </div>
                <div class="col-md-1">
                    <button class="btn btn-sm btn-secondary w-100" id="btnReset">
                        <i class="ki-outline ki-arrows-circle fs-6"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <x-data-table.table table-id="attendanceTable">
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Store</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Hours</th>
                        <th>Status</th>
                        <th>Late</th>
                        <th>Actions</th>
                    </x-data-table.table>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}">
@endsection
@section('vendor-scripts')
    <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
@endsection
@section('scripts')
    <script>
        $(document).ready(function() {
            const table = $('#attendanceTable');
            var startDate = moment().startOf('month');
            var endDate = moment().endOf('month');

            init();

            function init() {
                initDateRangePicker();
                initTable();
                handlers();
            }

            function initDateRangePicker() {
                $('#filterDateFrom').val(startDate.format('YYYY-MM-DD'));
                $('#filterDateTo').val(endDate.format('YYYY-MM-DD'));

                $('#daterangepicker').daterangepicker({
                    startDate: startDate,
                    endDate: endDate,
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
                }, function(start, end) {
                    startDate = start;
                    endDate = end;
                    $('#filterDateFrom').val(start.format('YYYY-MM-DD'));
                    $('#filterDateTo').val(end.format('YYYY-MM-DD'));
                    table.DataTable().ajax.reload();
                });
            }

            function initTable() {
                $('#tableSearch').keyup(function() {
                    table.DataTable().search($(this).val()).draw();
                });

                table.DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '{{ route("attendance.table") }}',
                        data: function(d) {
                            d.store_id = $('#filterStore').val();
                            d.user_id = $('#filterEmployee').val();
                            d.status = $('#filterStatus').val();
                            d.date_from = $('#filterDateFrom').val();
                            d.date_to = $('#filterDateTo').val();
                        }
                    },
                    columns: [
                        { data: 'formatted_date', name: 'date' },
                        { data: 'employee_name', name: 'user.name' },
                        { data: 'store_name', name: 'store.name' },
                        { data: 'time_in_formatted', name: 'time_in' },
                        { data: 'time_out_formatted', name: 'time_out' },
                        { data: 'formatted_hours', name: 'hours_rendered' },
                        { data: 'status_badge', name: 'status' },
                        { data: 'late_badge', name: 'is_late' },
                        { data: 'actions', orderable: false, searchable: false },
                    ],
                    order: [[0, 'desc']],
                    pageLength: 25,
                });

                initExportButton();
            }

            function initExportButton() {
                $('#exportCsv').on('click', function(e) {
                    e.preventDefault();
                    const params = new URLSearchParams({
                        store_id: $('#filterStore').val(),
                        user_id: $('#filterEmployee').val(),
                        status: $('#filterStatus').val(),
                        date_from: $('#filterDateFrom').val(),
                        date_to: $('#filterDateTo').val(),
                    });
                    window.location.href = '{{ route("attendance.export") }}?' + params.toString();
                });
            }

            function handlers() {
                $('#filterStore, #filterEmployee, #filterStatus').on('change', function() {
                    table.DataTable().ajax.reload();
                });

                $('#btnReset').on('click', function() {
                    $('#filterStore').val('');
                    $('#filterEmployee').val('');
                    $('#filterStatus').val('');
                    startDate = moment().startOf('month');
                    endDate = moment().endOf('month');
                    $('#daterangepicker').data('daterangepicker').setStartDate(startDate);
                    $('#daterangepicker').data('daterangepicker').setEndDate(endDate);
                    $('#filterDateFrom').val(startDate.format('YYYY-MM-DD'));
                    $('#filterDateTo').val(endDate.format('YYYY-MM-DD'));
                    table.DataTable().ajax.reload();
                });
            }
        });
    </script>
@endsection
