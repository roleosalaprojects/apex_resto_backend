@extends('layout.app')
@section('header')
    - Profit Margins
@endsection
@section('title')
    Profit Margins
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted"><span class="">Reports</span></li>
    <li class="breadcrumb-item text-muted">Profit Margins</li>
@endsection
@section('actions')
    <x-data-table.actions>
        <!--begin::Header-->
        <div class="px-7 py-5">
            <div class="fs-5 text-dark fw-bold">Filter Options</div>
        </div>
        <!--end::Header-->
        <!--begin::Menu separator-->
        <div class="separator border-gray-200"></div>
        <!--end::Menu separator-->
        <!--begin::Form-->
        <div class="px-7 py-5">
            <!--begin::Input group-->
            <div class="mb-3">
                <!--begin::Label-->
                <label class="form-label fw-semibold">Select Store:</label>
                <!--end::Label-->
                <!--begin::Input-->
                <div>
                    <select class="form-select form-select-solid select2-hidden-accessible" id="store_select" data-kt-select2="true" data-placeholder="Select option" data-allow-clear="true" tabindex="-1" aria-hidden="true" data-kt-initialized="1">
                        <option></option>
                    </select>
                </div>
                <!--end::Input-->
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="mb-3">
                <!--begin::Label-->
                <label for="daterangepicker" class="form-label fw-semibold">Date Range:</label>
                <!--end::Label-->
                <!--begin::Input-->
                <input class="form-control form-control-solid" placeholder="Pick date range" id="daterangepicker"/>
                <!--end::Input-->
            </div>
            <!--end::Input group-->
        </div>
        <!--end::Form-->
        <!--begin::Menu separator-->
        <div class="separator border-gray-200"></div>
        <!--end::Menu separator-->
    </x-data-table.actions>
    <x-general.search-table title="Item"></x-general.search-table>
@endsection
@section('content')
    {{-- Margin Alerts --}}
    @if(!empty($alerts['alerts']))
    <div class="card card-bordered border-warning mb-7">
        <!--begin::Header-->
        <div class="card-header bg-light-warning">
            <h3 class="card-title text-warning fw-bold">Margin Alerts (> 5% drop)</h3>
        </div>
        <!--end::Header-->
        <!--begin::Body-->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-row-bordered align-middle gs-7 gy-3 mb-0">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th>Item</th>
                            <th>Old Margin</th>
                            <th>New Margin</th>
                            <th>Drop</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($alerts['alerts'] as $alert)
                        <tr>
                            <td class="fw-semibold"><a href="{{ route('items.show', $alert['item_id']) }}" target="_blank" class="text-primary text-hover-dark">{{ $alert['item_name'] }}</a></td>
                            <td>{{ number_format($alert['old_margin'], 2) }}%</td>
                            <td>{{ number_format($alert['new_margin'], 2) }}%</td>
                            <td class="text-danger fw-bold">-{{ number_format($alert['margin_drop_pct'], 2) }}%</td>
                            <td><span class="badge badge-light-warning">{{ $alert['reason'] }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <!--end::Body-->
    </div>
    @endif

    {{-- Margin Chart --}}
    <div class="card card-bordered mb-7">
        <!--begin::Header-->
        <div class="card-header">
            <h3 class="card-title">Margin Distribution</h3>
        </div>
        <!--end::Header-->
        <!--begin::Body-->
        <div class="card-body">
            <div id="margin_chart" style="height: 400px;"></div>
        </div>
        <!--end::Body-->
    </div>

    {{-- Margins Table --}}
    <div class="card card-flush">
        <!--begin::Body-->
        <div class="card-body">
            <x-data-table.table table-id="table">
                <th>Item</th>
                <th>Current Margin %</th>
                <th>Previous Margin %</th>
                <th>Change</th>
                <th>Cost</th>
                <th>Price</th>
                <th>Qty Sold</th>
                <th>Total Profit</th>
            </x-data-table.table>
        </div>
        <!--end::Body-->
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
            var storeSelect = $('#store_select');
            var startDate = moment().subtract(29, 'days').format('YYYY-MM-DD');
            var endDate = moment().format('YYYY-MM-DD');

            var labelColor = KTUtil.getCssVariableValue('--bs-gray-500');
            var borderColor = KTUtil.getCssVariableValue('--bs-gray-200');
            var primaryColor = KTUtil.getCssVariableValue('--bs-primary');
            var successColor = KTUtil.getCssVariableValue('--bs-success');

            storeSelect.select2({
                dropdownParent: $('#datatables_menu'),
                ajax: {
                    url: "{{ route('stores.select') }}",
                    type: 'GET',
                    delay: 250,
                    dataType: 'JSON',
                    data: function(params) {
                        return { term: params.term };
                    },
                    processResults: function(data) {
                        return { results: data };
                    },
                }
            });

            function numberWithCommas(x) {
                return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }

            // Initialize chart with noData
            var marginElement = document.getElementById('margin_chart');
            var marginChart = new ApexCharts(marginElement, {
                chart: {
                    fontFamily: 'inherit',
                    type: 'bar',
                    height: 400,
                    toolbar: { show: true }
                },
                series: [],
                noData: { text: 'Loading...' }
            });
            marginChart.render();

            var tableOptions = {
                responsive: true,
                serverside: false,
                processing: true,
                columns: [
                    {
                        data: null,
                        render: function(data) {
                            return '<a href="/admin/items/' + data.item_id + '" target="_blank" class="text-primary fw-semibold text-hover-dark">' + data.item_name + '</a>';
                        }
                    },
                    { data: 'current_margin_pct' },
                    { data: 'previous_margin_pct' },
                    { data: 'margin_change' },
                    { data: 'current_cost' },
                    { data: 'current_price' },
                    { data: 'total_sold' },
                    { data: 'total_profit' },
                ],
                columnDefs: [
                    {
                        targets: 1,
                        render: function(data) {
                            if (data === null) return '-';
                            var cls = data >= 20 ? 'badge-light-success' : (data >= 10 ? 'badge-light-warning' : 'badge-light-danger');
                            return '<span class="badge ' + cls + '">' + data.toFixed(2) + '%</span>';
                        }
                    },
                    {
                        targets: 2,
                        render: function(data) {
                            return data !== null ? data.toFixed(2) + '%' : '-';
                        }
                    },
                    {
                        targets: 3,
                        render: function(data) {
                            if (data === null) return '-';
                            var cls = data >= 0 ? 'text-success' : 'text-danger';
                            var arrow = data >= 0 ? '&#9650;' : '&#9660;';
                            return '<span class="' + cls + ' fw-bold">' + arrow + ' ' + Math.abs(data).toFixed(2) + '%</span>';
                        }
                    },
                    {
                        targets: [4, 5],
                        render: function(data) {
                            return '₱ ' + numberWithCommas(data.toFixed(2));
                        }
                    },
                    {
                        targets: 7,
                        render: function(data) {
                            return '₱ ' + numberWithCommas(data.toFixed(2));
                        }
                    }
                ],
                order: [[3, 'asc']],
                ajax: {
                    type: 'get',
                    url: "{{ route('reports.profit_margins.data') }}",
                    data: function(d) {
                        d.start_date = startDate;
                        d.end_date = endDate;
                        d.store_id = storeSelect.val();
                    },
                    dataSrc: function(response) {
                        updateMarginChart(response.data);
                        return response.data;
                    }
                }
            };

            var table = $('#table');
            var dataTable = table.DataTable(tableOptions);

            function updateMarginChart(data) {
                var categories = data.slice(0, 20).map(function(d) { return d.item_name.substring(0, 15); });
                var margins = data.slice(0, 20).map(function(d) { return d.current_margin_pct; });
                var profits = data.slice(0, 20).map(function(d) { return d.total_profit; });

                marginChart.updateOptions({
                    series: [
                        { name: 'Margin %', type: 'column', data: margins },
                        { name: 'Profit (₱)', type: 'line', data: profits }
                    ],
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            borderRadius: 4,
                            columnWidth: '60%'
                        }
                    },
                    xaxis: {
                        categories: categories,
                        labels: {
                            rotate: -45,
                            style: { colors: labelColor, fontSize: '12px' }
                        },
                        axisBorder: { show: false },
                        axisTicks: { show: false }
                    },
                    yaxis: [
                        {
                            title: { text: 'Margin %' },
                            labels: {
                                style: { colors: labelColor, fontSize: '12px' }
                            }
                        },
                        {
                            opposite: true,
                            title: { text: 'Profit (₱)' },
                            labels: {
                                style: { colors: labelColor, fontSize: '12px' },
                                formatter: function(v) { return '₱' + numberWithCommas(Math.round(v)); }
                            }
                        }
                    ],
                    colors: [primaryColor, successColor],
                    stroke: {
                        curve: 'smooth',
                        show: true,
                        width: [0, 3]
                    },
                    tooltip: {
                        style: { fontSize: '12px' },
                        y: {
                            formatter: function(v, opts) {
                                if (opts.seriesIndex === 1) return '₱ ' + numberWithCommas(v.toFixed(2));
                                return v.toFixed(2) + '%';
                            }
                        }
                    },
                    grid: {
                        borderColor: borderColor,
                        strokeDashArray: 4,
                        yaxis: { lines: { show: true } }
                    },
                    noData: { text: 'No data available' }
                });
            }

            var documentTitle = 'Profit Margins Report';
            var buttons = new $.fn.dataTable.Buttons(table, {
                buttons: [
                    { extend: 'copyHtml5', title: documentTitle },
                    { extend: 'excelHtml5', title: documentTitle },
                    { extend: 'csvHtml5', title: documentTitle },
                    { extend: 'pdfHtml5', title: documentTitle }
                ]
            }).container().appendTo($('#datatable_buttons'));

            var exportButtons = document.querySelectorAll('#datatables_menu [data-kt-export]');
            exportButtons.forEach(function(exportButton) {
                exportButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    var exportValue = e.target.getAttribute('data-kt-export');
                    var target = document.querySelector('.dt-buttons .buttons-' + exportValue);
                    target.click();
                });
            });

            $('#daterangepicker').daterangepicker({
                startDate: moment().subtract(29, 'days'),
                endDate: moment(),
                showDropdowns: true,
                ranges: {
                    "Today": [moment(), moment()],
                    "Yesterday": [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    "Last 7 Days": [moment().subtract(6, 'days'), moment()],
                    "Last 30 Days": [moment().subtract(29, 'days'), moment()],
                    "This Month": [moment().startOf('month'), moment().endOf('month')],
                    "Last Month": [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                    "Last 3 Months": [moment().subtract(3, 'months').startOf('month'), moment()],
                    "Last 6 Months": [moment().subtract(6, 'months').startOf('month'), moment()],
                    "This Year": [moment().startOf('year'), moment().endOf('year')],
                    "Last Year": [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
                }
            }, function(start, end) {
                startDate = start.format('YYYY-MM-DD');
                endDate = end.format('YYYY-MM-DD');
                dataTable.ajax.reload();
            });

            storeSelect.on('select2:select select2:clear', function() { dataTable.ajax.reload(); });

            $('#tableSearch').keyup(function() { dataTable.search($(this).val()).draw(); });
        });
    </script>
@endsection
