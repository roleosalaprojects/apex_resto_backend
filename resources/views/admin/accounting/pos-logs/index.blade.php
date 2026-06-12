@extends('layout.app')
@section('header')
    - POS Logs
@endsection
@section('title')
    POS Logs
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">POS Logs</li>
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
    <x-general.search-table title="POS Log"></x-general.search-table>
@endsection
@section('content')
    <div class="card mb-6">
        <div class="card-body py-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fs-7 fw-semibold">Type</label>
                    <select class="form-select form-select-sm" id="filterType">
                        <option value="">All Types</option>
                        @foreach($typeLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
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
                    <label class="form-label fs-7 fw-semibold">Terminal</label>
                    <select class="form-select form-select-sm" id="filterTerminal">
                        <option value="">All Terminals</option>
                        @foreach($terminals as $terminal)
                            <option value="{{ $terminal->id }}">{{ $terminal->name }}</option>
                        @endforeach
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
                    <x-data-table.table table-id="posLogsTable">
                        <th>Date</th>
                        <th>Type</th>
                        <th>Terminal</th>
                        <th>Store</th>
                        <th>Employee</th>
                        <th class="text-end">Cash In</th>
                        <th class="text-end">Cash Out</th>
                        <th>Reason</th>
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
            const table = $('#posLogsTable');
            var startDate = moment();
            var endDate = moment();

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
                        url: '{{ route("pos-logs.table") }}',
                        data: function(d) {
                            d.type = $('#filterType').val();
                            d.store_id = $('#filterStore').val();
                            d.pos_id = $('#filterTerminal').val();
                            d.date_from = $('#filterDateFrom').val();
                            d.date_to = $('#filterDateTo').val();
                        }
                    },
                    columns: [
                        { data: 'formatted_date', name: 'created_at' },
                        { data: 'type_badge', name: 'type' },
                        { data: 'terminal_name', name: 'pos.name' },
                        { data: 'store_name', name: 'store.name' },
                        { data: 'employee_name', name: 'user.name' },
                        { data: 'formatted_cash_in', name: 'cash_in', className: 'text-end' },
                        { data: 'formatted_cash_out', name: 'cash_out', className: 'text-end' },
                        { data: 'reason_text', name: 'reason' },
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
                        type: $('#filterType').val(),
                        store_id: $('#filterStore').val(),
                        pos_id: $('#filterTerminal').val(),
                        date_from: $('#filterDateFrom').val(),
                        date_to: $('#filterDateTo').val(),
                    });
                    window.location.href = '{{ route("pos-logs.export") }}?' + params.toString();
                });
            }

            function handlers() {
                $('#filterType, #filterStore, #filterTerminal').on('change', function() {
                    table.DataTable().ajax.reload();
                });

                $('#btnReset').on('click', function() {
                    $('#filterType').val('');
                    $('#filterStore').val('');
                    $('#filterTerminal').val('');
                    startDate = moment();
                    endDate = moment();
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
