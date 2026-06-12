@extends('layout.app')
@section('header')
    - Year by Year Comparison
@endsection
@section('title')
    Year by Year Comparison
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted"><span class="">Reports</span></li>
    <li class="breadcrumb-item text-muted">Year by Year Comparison</li>
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
                <label class="form-label fw-semibold">Select Store:</label>
                <div>
                    <select class="form-select form-select-solid select2-hidden-accessible" id="store_select" data-kt-select2="true" data-placeholder="Select option" data-allow-clear="true" tabindex="-1" aria-hidden="true" data-kt-initialized="1">
                        <option></option>
                    </select>
                </div>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="mb-3">
                <label for="end_year" class="form-label fw-semibold">Latest Year:</label>
                <select class="form-select form-select-solid" id="end_year">
                    @php($currentYear = (int) now()->year)
                    @for ($y = $currentYear; $y >= $currentYear - 9; $y--)
                        <option value="{{ $y }}" @selected($y === $currentYear)>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="mb-3">
                <label for="years_count" class="form-label fw-semibold">Years to Compare:</label>
                <select class="form-select form-select-solid" id="years_count">
                    @for ($n = 2; $n <= 10; $n++)
                        <option value="{{ $n }}" @selected($n === 3)>{{ $n }} years</option>
                    @endfor
                </select>
            </div>
            <!--end::Input group-->
        </div>
        <!--end::Form-->
        <!--begin::Menu separator-->
        <div class="separator border-gray-200"></div>
        <!--end::Menu separator-->
    </x-data-table.actions>
    <x-general.search-table title="Year"></x-general.search-table>
@endsection
@section('content')
    {{-- Statistics --}}
    <div class="row g-5 g-xl-8">
        <div class="col-xl-3">
            <div class="card bg-light-primary hoverable card-xl-stretch mb-xl-8">
                <div class="card-body">
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <i class="ki-outline ki-calendar fs-3x"></i>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-8" id="latestYear">—</div>
                    <div class="fw-semibold text-gray-600 fs-3">Latest Year</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card bg-light-success hoverable card-xl-stretch mb-xl-8">
                <div class="card-body">
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <i class="ki-outline ki-chart-line-up fs-3x"></i>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-8" id="latestNetSales">₱ 0.00</div>
                    <div class="fw-semibold text-gray-900 fs-3">Net Sales (Latest)</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card bg-light-info hoverable card-xl-stretch mb-xl-8">
                <div class="card-body">
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <i class="ki-outline ki-dollar fs-3x"></i>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-8" id="latestProfit">₱ 0.00</div>
                    <div class="fw-semibold text-gray-900 fs-3">Profit (Latest)</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card bg-light-warning hoverable card-xl-stretch mb-xl-8">
                <div class="card-body">
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <i class="ki-outline ki-arrow-up fs-3x"></i>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-8" id="yoyGrowth">—</div>
                    <div class="fw-semibold text-gray-900 fs-3">YoY Growth</div>
                </div>
            </div>
        </div>
    </div>
    {{-- Chart --}}
    <div class="card card-bordered mb-7">
        <div class="card-header">
            <h3 class="card-title fw-bold">Monthly Net Sales Comparison</h3>
        </div>
        <div class="card-body">
            <div id="comparison_chart" style="height: 500px;"></div>
        </div>
    </div>
    {{-- Yearly Table --}}
    <div class="card card-flush mb-7">
        <div class="card-body">
            <x-data-table.table table-id="table">
                <th>Year</th>
                <th>Sales</th>
                <th>Refunds</th>
                <th>Net Sales</th>
                <th>Profit</th>
                <th>Receipts</th>
                <th>YoY Growth</th>
            </x-data-table.table>
        </div>
    </div>
    {{-- Monthly Comparison Table --}}
    <div class="card card-flush">
        <div class="card-header">
            <h3 class="card-title fw-bold">Monthly Comparison Across Years</h3>
            <div class="card-toolbar">
                <div class="btn-group btn-group-sm" role="group" aria-label="Metric selector" id="monthly_metric_switcher">
                    <button type="button" class="btn btn-light-primary active" data-metric="net_sales">Net Sales</button>
                    <button type="button" class="btn btn-light-primary" data-metric="sales">Sales</button>
                    <button type="button" class="btn btn-light-primary" data-metric="refunds">Refunds</button>
                    <button type="button" class="btn btn-light-primary" data-metric="profit">Profit</button>
                    <button type="button" class="btn btn-light-primary" data-metric="receipts">Receipts</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="text-muted fs-7 mb-3" id="monthly_metric_caption">Each year column shows net sales and the % change vs the same month in the prior year.</div>
            <div class="table-responsive">
                <table class="table table-row-bordered align-middle gy-3 gs-3 mb-0" id="monthly_comparison_table">
                    <thead class="fs-7 text-muted text-uppercase">
                        <tr id="monthly_comparison_header"></tr>
                    </thead>
                    <tbody id="monthly_comparison_body"></tbody>
                </table>
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
        $(document).ready(function () {
            var store_select = $('#store_select');
            var end_year_select = $('#end_year');
            var years_count_select = $('#years_count');
            var monthlyMetric = 'net_sales';
            var lastMonthlyResponse = { years: [], monthly_rows: [] };

            var metricLabels = {
                sales: 'Sales',
                refunds: 'Refunds',
                net_sales: 'Net Sales',
                profit: 'Profit',
                receipts: 'Receipts'
            };

            function formatMetricValue(metric, value) {
                if (metric === 'receipts') {
                    return numberWithCommas(parseInt(value || 0));
                }
                return '₱ ' + numberWithCommas(parseFloat(value || 0).toFixed(2));
            }

            store_select.select2({
                dropdownParent: $('#datatables_menu'),
                ajax: {
                    url: "{{ route('stores.select') }}",
                    type: 'GET',
                    delay: 250,
                    dataType: 'JSON',
                    data: function (params) {
                        return { term: params.term };
                    },
                    processResults: function (data) {
                        return { results: data };
                    }
                }
            });

            var element = document.getElementById('comparison_chart');
            var height = parseInt(KTUtil.css(element, 'height'));
            var labelColor = KTUtil.getCssVariableValue('--bs-gray-500');
            var borderColor = KTUtil.getCssVariableValue('--bs-gray-200');

            var chartPalette = [
                KTUtil.getCssVariableValue('--bs-primary'),
                KTUtil.getCssVariableValue('--bs-success'),
                KTUtil.getCssVariableValue('--bs-warning'),
                KTUtil.getCssVariableValue('--bs-danger'),
                KTUtil.getCssVariableValue('--bs-info'),
                '#7E57C2',
                '#26A69A',
                '#EC407A',
                '#5C6BC0',
                '#8D6E63'
            ];

            var monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            var options = {
                chart: {
                    fontFamily: 'inherit',
                    type: 'line',
                    height: height,
                    toolbar: { show: true }
                },
                stroke: { curve: 'smooth', width: 3 },
                dataLabels: { enabled: false },
                xaxis: {
                    categories: monthLabels,
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: { style: { colors: labelColor, fontSize: '12px' } }
                },
                yaxis: {
                    labels: {
                        style: { colors: labelColor, fontSize: '12px' },
                        formatter: function (value) {
                            return convertNumberShorter(value);
                        }
                    }
                },
                tooltip: {
                    style: { fontSize: '12px' },
                    y: {
                        formatter: function (val) {
                            return numberWithCommas((val ?? 0).toFixed(2));
                        }
                    }
                },
                legend: { show: true, position: 'top' },
                colors: chartPalette,
                grid: {
                    borderColor: borderColor,
                    strokeDashArray: 4,
                    yaxis: { lines: { show: true } }
                },
                series: [],
                noData: { text: 'Loading...' }
            };

            var chart = new ApexCharts(element, options);
            chart.render();

            var tableOptions = {
                filter: true,
                responsive: true,
                serverside: false,
                processing: true,
                ordering: false,
                paging: false,
                searching: false,
                info: false,
                footerCallback: function (row, data) {
                    if (!data || !data.length) {
                        return;
                    }
                    var totals = data.reduce(function (acc, r) {
                        acc.sales += parseFloat(r.sales || 0);
                        acc.refunds += parseFloat(r.refunds || 0);
                        acc.net_sales += parseFloat(r.net_sales || 0);
                        acc.profit += parseFloat(r.profit || 0);
                        acc.receipts += parseInt(r.receipts || 0);
                        return acc;
                    }, { sales: 0, refunds: 0, net_sales: 0, profit: 0, receipts: 0 });

                    var firstNet = parseFloat(data[0].net_sales || 0);
                    var lastNet = parseFloat(data[data.length - 1].net_sales || 0);
                    var overallGrowth = (data.length > 1 && firstNet != 0)
                        ? ((lastNet - firstNet) / firstNet) * 100
                        : null;

                    $(row).find('th').eq(0).html('Total');
                    $(row).find('th').eq(1).html('₱ ' + numberWithCommas(totals.sales.toFixed(2)));
                    $(row).find('th').eq(2).html('₱ ' + numberWithCommas(totals.refunds.toFixed(2)));
                    $(row).find('th').eq(3).html('₱ ' + numberWithCommas(totals.net_sales.toFixed(2)));
                    $(row).find('th').eq(4).html('₱ ' + numberWithCommas(totals.profit.toFixed(2)));
                    $(row).find('th').eq(5).html(numberWithCommas(totals.receipts));
                    if (overallGrowth === null) {
                        $(row).find('th').eq(6).html('<span class="text-muted">—</span>');
                    } else {
                        var overallSign = overallGrowth >= 0 ? '+' : '';
                        var overallClass = overallGrowth >= 0 ? 'text-success' : 'text-danger';
                        $(row).find('th').eq(6).html('<span class="' + overallClass + ' fw-bold">' + overallSign + overallGrowth.toFixed(2) + ' %</span>');
                    }
                },
                columns: [
                    { data: 'year' },
                    { data: 'sales' },
                    { data: 'refunds' },
                    { data: 'net_sales' },
                    { data: 'profit' },
                    { data: 'receipts' },
                    { data: 'growth' }
                ],
                columnDefs: [
                    { targets: 1, render: function (data) { return '₱ ' + numberWithCommas(parseFloat(data || 0).toFixed(2)); } },
                    { targets: 2, render: function (data) { return '₱ ' + numberWithCommas(parseFloat(data || 0).toFixed(2)); } },
                    { targets: 3, render: function (data) { return '₱ ' + numberWithCommas(parseFloat(data || 0).toFixed(2)); } },
                    { targets: 4, render: function (data) { return '₱ ' + numberWithCommas(parseFloat(data || 0).toFixed(2)); } },
                    { targets: 5, render: function (data) { return numberWithCommas(parseInt(data || 0)); } },
                    {
                        targets: 6,
                        render: function (data) {
                            if (data === null || typeof data === 'undefined') {
                                return '<span class="text-muted">—</span>';
                            }
                            var value = parseFloat(data);
                            var cls = value >= 0 ? 'text-success' : 'text-danger';
                            var sign = value >= 0 ? '+' : '';
                            return '<span class="' + cls + ' fw-bold">' + sign + value.toFixed(2) + ' %</span>';
                        }
                    }
                ],
                ajax: {
                    type: 'get',
                    data: {
                        end_year: function () { return end_year_select.val(); },
                        years_count: function () { return years_count_select.val(); },
                        store_select: function () { return store_select.val(); }
                    },
                    url: "{{ route('reports.year_by_year_comparison.data') }}",
                    dataSrc: function (response) {
                        var summary = response.summary || {};
                        $('#latestYear').text(summary.latest_year || '—');
                        $('#latestNetSales').text('₱ ' + numberWithCommas(parseFloat(summary.latest_net_sales || 0).toFixed(2)));
                        $('#latestProfit').text('₱ ' + numberWithCommas(parseFloat(summary.latest_profit || 0).toFixed(2)));

                        if (summary.yoy_growth === null || typeof summary.yoy_growth === 'undefined') {
                            $('#yoyGrowth').text('—').removeClass('text-success text-danger');
                        } else {
                            var growth = parseFloat(summary.yoy_growth);
                            var sign = growth >= 0 ? '+' : '';
                            $('#yoyGrowth').text(sign + growth.toFixed(2) + ' %');
                        }

                        chart.updateOptions({
                            series: (response.series || []).map(function (s) {
                                return { name: String(s.year), data: s.data };
                            })
                        });

                        lastMonthlyResponse = {
                            years: response.years || [],
                            monthly_rows: response.monthly_rows || []
                        };
                        renderMonthlyComparison();

                        return response.rows || [];
                    }
                }
            };

            var table = $('#table');
            table.append('<tfoot class="fw-bold fs-6 text-gray-800 border-top border-gray-200"><tr><th></th><th></th><th></th><th></th><th></th><th></th><th></th></tr></tfoot>');
            var dataTable = table.DataTable(tableOptions);
            const documentTitle = 'Year by Year Comparison Report';
            new $.fn.dataTable.Buttons(table, {
                buttons: [
                    { extend: 'copyHtml5', title: documentTitle },
                    { extend: 'excelHtml5', title: documentTitle },
                    { extend: 'csvHtml5', title: documentTitle },
                    { extend: 'pdfHtml5', title: documentTitle }
                ]
            }).container().appendTo($('#datatable_buttons'));

            const exportButtons = document.querySelectorAll('#datatables_menu [data-kt-export]');
            exportButtons.forEach(exportButton => {
                exportButton.addEventListener('click', e => {
                    e.preventDefault();
                    const exportValue = e.target.getAttribute('data-kt-export');
                    const target = document.querySelector('.dt-buttons .buttons-' + exportValue);
                    target.click();
                });
            });

            store_select.on('select2:select select2:clear', function () { table.DataTable().ajax.reload(); });
            end_year_select.on('change', function () { table.DataTable().ajax.reload(); });
            years_count_select.on('change', function () { table.DataTable().ajax.reload(); });

            $('#monthly_metric_switcher button').on('click', function () {
                var $btn = $(this);
                if ($btn.hasClass('active')) {
                    return;
                }
                $('#monthly_metric_switcher button').removeClass('active');
                $btn.addClass('active');
                monthlyMetric = $btn.data('metric');
                renderMonthlyComparison();
            });

            $('#tableSearch').keyup(function () { table.DataTable().search($(this).val()).draw(); });

            function numberWithCommas(x) {
                return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            function renderMonthlyComparison() {
                var years = lastMonthlyResponse.years || [];
                var rows = lastMonthlyResponse.monthly_rows || [];
                var metric = monthlyMetric;
                var growthKey = metric + '_growth';
                var label = metricLabels[metric] || metric;

                $('#monthly_metric_caption').text('Each year column shows ' + label.toLowerCase() + ' and the % change vs the same month in the prior year.');

                var $header = $('#monthly_comparison_header');
                var $body = $('#monthly_comparison_body');
                $header.empty();
                $body.empty();

                if (!years.length) {
                    $body.append('<tr><td class="text-center text-muted">No data available.</td></tr>');
                    return;
                }

                var headerHtml = '<th class="min-w-100px">Month</th>';
                years.forEach(function (year, index) {
                    var yearLabel = escapeHtml(year);
                    if (index === 0) {
                        headerHtml += '<th class="min-w-120px">' + yearLabel + '<div class="text-muted fw-normal fs-8">' + escapeHtml(label.toLowerCase()) + '</div></th>';
                    } else {
                        headerHtml += '<th class="min-w-160px">' + yearLabel + '<div class="text-muted fw-normal fs-8">' + escapeHtml(label.toLowerCase()) + ' · YoY %</div></th>';
                    }
                });
                $header.append(headerHtml);

                var yearTotals = years.map(function () { return 0; });

                rows.forEach(function (row) {
                    var rowHtml = '<tr>';
                    rowHtml += '<td class="fw-bold">' + escapeHtml(row.month) + '</td>';
                    row.values.forEach(function (cell, index) {
                        var rawValue = parseFloat(cell[metric] || 0);
                        yearTotals[index] += rawValue;
                        var amount = formatMetricValue(metric, cell[metric]);
                        if (index === 0) {
                            rowHtml += '<td>' + amount + '</td>';
                        } else {
                            var growth = cell[growthKey];
                            var growthHtml;
                            if (growth === null || typeof growth === 'undefined') {
                                growthHtml = '<span class="badge badge-light-secondary fs-8 ms-2">—</span>';
                            } else {
                                var value = parseFloat(growth);
                                var badgeClass = value >= 0 ? 'badge-light-success' : 'badge-light-danger';
                                var sign = value >= 0 ? '+' : '';
                                growthHtml = '<span class="badge ' + badgeClass + ' fs-8 ms-2">' + sign + value.toFixed(2) + ' %</span>';
                            }
                            rowHtml += '<td>' + amount + growthHtml + '</td>';
                        }
                    });
                    rowHtml += '</tr>';
                    $body.append(rowHtml);
                });

                var totalsHtml = '<tr class="fw-bold border-top">';
                totalsHtml += '<td class="fw-bold">Total</td>';
                yearTotals.forEach(function (total, index) {
                    var amount = formatMetricValue(metric, total);
                    if (index === 0) {
                        totalsHtml += '<td>' + amount + '</td>';
                    } else {
                        var prevTotal = yearTotals[index - 1];
                        var growthHtml;
                        if (prevTotal === 0) {
                            growthHtml = '<span class="badge badge-light-secondary fs-8 ms-2">—</span>';
                        } else {
                            var growthValue = ((total - prevTotal) / prevTotal) * 100;
                            var badgeClass = growthValue >= 0 ? 'badge-light-success' : 'badge-light-danger';
                            var sign = growthValue >= 0 ? '+' : '';
                            growthHtml = '<span class="badge ' + badgeClass + ' fs-8 ms-2">' + sign + growthValue.toFixed(2) + ' %</span>';
                        }
                        totalsHtml += '<td>' + amount + growthHtml + '</td>';
                    }
                });
                totalsHtml += '</tr>';
                $body.append(totalsHtml);
            }
        });
    </script>
@endsection
