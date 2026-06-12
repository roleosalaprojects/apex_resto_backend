@extends('layout.app')
@section('header')
    - Business Health
@endsection
@section('title')
    Business Health
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted"><span class="">Reports</span></li>
    <li class="breadcrumb-item text-muted">Business Health</li>
@endsection
@section('actions')
    <x-data-table.actions :show-export="false">
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
        <!--begin::Export-->
        <div class="px-7 py-5">
            <a href="{{ route('reports.business_intelligence.export') }}" class="btn btn-light-success w-100" id="export_btn">
                <i class="ki-outline ki-file-down fs-4"></i> Export CSV
            </a>
        </div>
        <!--end::Export-->
    </x-data-table.actions>
@endsection
@section('content')
    {{-- Summary Cards --}}
    <div class="row g-5 g-xl-8 mb-7">
        <div class="col-xl-3">
            <!--begin::Statistics Widget 5-->
            <div class="card bg-light-primary hoverable card-xl-stretch mb-xl-8">
                <!--begin::Body-->
                <div class="card-body">
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 8H4C3.4 8 3 8.4 3 9V19C3 19.6 3.4 20 4 20H20C20.6 20 21 19.6 21 19V9C21 8.4 20.6 8 20 8ZM12 17C10.3 17 9 15.7 9 14C9 12.3 10.3 11 12 11C13.7 11 15 12.3 15 14C15 15.7 13.7 17 12 17Z" fill="currentColor"/>
                            <path opacity="0.3" d="M20 5H4C3.4 5 3 5.4 3 6V8H21V6C21 5.4 20.6 5 20 5Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-5" id="net_sales_value">-</div>
                    <div class="fw-semibold text-gray-600 fs-7">Net Sales <span id="net_sales_change"></span></div>
                </div>
                <!--end::Body-->
            </div>
            <!--end::Statistics Widget 5-->
        </div>
        <div class="col-xl-3">
            <!--begin::Statistics Widget 5-->
            <div class="card bg-light-success hoverable card-xl-stretch mb-xl-8">
                <!--begin::Body-->
                <div class="card-body">
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17.8 8.79999L13 13.6L9.7 10.3C9.3 9.89999 8.7 9.89999 8.3 10.3L2.3 16.3C1.9 16.7 1.9 17.3 2.3 17.7C2.5 17.9 2.7 18 3 18C3.3 18 3.5 17.9 3.7 17.7L9 12.4L12.3 15.7C12.7 16.1 13.3 16.1 13.7 15.7L19.2 10.2L17.8 8.79999Z" fill="currentColor"/>
                            <path opacity="0.3" d="M22 13.1V7C22 6.4 21.6 6 21 6H14.9L22 13.1Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-5" id="gross_profit_value">-</div>
                    <div class="fw-semibold text-gray-600 fs-7">Gross Profit <span id="gross_margin_display"></span></div>
                </div>
                <!--end::Body-->
            </div>
            <!--end::Statistics Widget 5-->
        </div>
        <div class="col-xl-3">
            <!--begin::Statistics Widget 5-->
            <div class="card bg-light-warning hoverable card-xl-stretch mb-xl-8">
                <!--begin::Body-->
                <div class="card-body">
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19.2 13.8L13.7 8.3C13.3 7.9 12.7 7.9 12.3 8.3L9 11.6L3.7 6.3C3.3 5.9 2.7 5.9 2.3 6.3C1.9 6.7 1.9 7.3 2.3 7.7L8.3 13.7C8.7 14.1 9.3 14.1 9.7 13.7L13 10.4L17.8 15.2L19.2 13.8Z" fill="currentColor"/>
                            <path opacity="0.3" d="M22 10.9V17C22 17.6 21.6 18 21 18H14.9L22 10.9Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-5" id="expenses_value">-</div>
                    <div class="fw-semibold text-gray-600 fs-7">Expenses</div>
                </div>
                <!--end::Body-->
            </div>
            <!--end::Statistics Widget 5-->
        </div>
        <div class="col-xl-3">
            <!--begin::Statistics Widget 5-->
            <div class="card bg-light-info hoverable card-xl-stretch mb-xl-8">
                <!--begin::Body-->
                <div class="card-body">
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13 5.91517C15.8 6.41517 18 8.81519 18 11.8152C18 12.5152 17.9 13.2152 17.6 13.9152L20.1 15.3152C20.6 15.6152 21.4 15.4152 21.6 14.8152C21.9 13.9152 22.1 12.9152 22.1 11.8152C22.1 7.01519 18.8 3.11521 14.3 2.01521C13.7 1.91521 13.1 2.31521 13.1 3.01521V5.91517H13Z" fill="currentColor"/>
                            <path opacity="0.3" d="M19.1 17.0152C19.7 17.3152 19.8 18.1152 19.3 18.5152C17.5 20.5152 14.9 21.7152 12 21.7152C6.50001 21.7152 2.10001 17.3152 2.10001 11.8152C2.10001 6.91519 5.60001 2.81519 10.2 2.01519C10.8 1.91519 11.4 2.31519 11.4 3.01519V11.2152L19.1 17.0152Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-5" id="net_profit_value">-</div>
                    <div class="fw-semibold text-gray-600 fs-7">Net Profit <span id="net_margin_display"></span> <span id="net_profit_change"></span></div>
                </div>
                <!--end::Body-->
            </div>
            <!--end::Statistics Widget 5-->
        </div>
    </div>

    {{-- P&L Trend --}}
    <div class="card card-bordered mb-7">
        <!--begin::Header-->
        <div class="card-header">
            <h3 class="card-title">Daily P&amp;L Trend</h3>
            <div class="card-toolbar">
                <span class="badge badge-light text-muted" id="data_through_badge">Data through: -</span>
            </div>
        </div>
        <!--end::Header-->
        <!--begin::Body-->
        <div class="card-body">
            <div id="trend_chart" style="height: 400px;"></div>
        </div>
        <!--end::Body-->
    </div>

    {{-- Payment Mix / Channels / Discounts --}}
    <div class="row g-5 g-xl-8 mb-7">
        <div class="col-xl-4">
            <div class="card card-bordered card-xl-stretch">
                <!--begin::Header-->
                <div class="card-header">
                    <h3 class="card-title">Payment Mix</h3>
                </div>
                <!--end::Header-->
                <!--begin::Body-->
                <div class="card-body">
                    <div id="payment_mix_chart" style="height: 300px;"></div>
                </div>
                <!--end::Body-->
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card card-bordered card-xl-stretch">
                <!--begin::Header-->
                <div class="card-header">
                    <h3 class="card-title">Sales Channels</h3>
                </div>
                <!--end::Header-->
                <!--begin::Body-->
                <div class="card-body">
                    <div id="channels_chart" style="height: 300px;"></div>
                </div>
                <!--end::Body-->
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card card-bordered card-xl-stretch">
                <!--begin::Header-->
                <div class="card-header">
                    <h3 class="card-title">Discounts Given</h3>
                </div>
                <!--end::Header-->
                <!--begin::Body-->
                <div class="card-body">
                    <div id="discounts_chart" style="height: 300px;"></div>
                </div>
                <!--end::Body-->
            </div>
        </div>
    </div>

    {{-- Top Items & Top Customers --}}
    <div class="row g-5 g-xl-8">
        <div class="col-xl-6">
            <div class="card card-bordered">
                <!--begin::Header-->
                <div class="card-header">
                    <h3 class="card-title">Top 10 Items by Revenue</h3>
                </div>
                <!--end::Header-->
                <!--begin::Body-->
                <div class="card-body py-3">
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-300 align-middle gs-0 gy-3" id="top_items_table">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>Item</th>
                                    <th class="text-end">Qty Sold</th>
                                    <th class="text-end">Revenue</th>
                                    <th class="text-end">Profit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="4" class="text-center text-muted py-5">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!--end::Body-->
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card card-bordered">
                <!--begin::Header-->
                <div class="card-header">
                    <h3 class="card-title">Top 10 Customers by Spend <span class="text-muted fs-7 fw-normal ms-2">(all stores)</span></h3>
                </div>
                <!--end::Header-->
                <!--begin::Body-->
                <div class="card-body py-3">
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-300 align-middle gs-0 gy-3" id="top_customers_table">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>Customer</th>
                                    <th class="text-end">Receipts</th>
                                    <th class="text-end">Spend</th>
                                    <th class="text-end">Profit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="4" class="text-center text-muted py-5">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!--end::Body-->
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        $(document).ready(function() {
            var storeSelect = $('#store_select');
            var startDate = moment().subtract(29, 'days').format('YYYY-MM-DD');
            var endDate = moment().format('YYYY-MM-DD');
            var trendChart, paymentMixChart, channelsChart, discountsChart;

            var labelColor = KTUtil.getCssVariableValue('--bs-gray-500');
            var borderColor = KTUtil.getCssVariableValue('--bs-gray-200');
            var primaryColor = KTUtil.getCssVariableValue('--bs-primary');
            var successColor = KTUtil.getCssVariableValue('--bs-success');
            var warningColor = KTUtil.getCssVariableValue('--bs-warning');
            var infoColor = KTUtil.getCssVariableValue('--bs-info');
            var dangerColor = KTUtil.getCssVariableValue('--bs-danger');

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

            function fmtCurrency(v) {
                return '₱ ' + numberWithCommas(parseFloat(v).toFixed(2));
            }

            function fmtChange(pct) {
                if (pct === null || pct === undefined) return '';
                var cls = pct >= 0 ? 'badge-light-success' : 'badge-light-danger';
                var arrow = pct >= 0 ? '▲' : '▼';
                return '<span class="badge ' + cls + ' ms-1">' + arrow + ' ' + Math.abs(pct).toFixed(1) + '%</span>';
            }

            // Initialize charts with noData message
            trendChart = new ApexCharts(document.getElementById('trend_chart'), {
                chart: { fontFamily: 'inherit', type: 'line', height: 400, toolbar: { show: true } },
                series: [],
                noData: { text: 'Loading...' }
            });
            trendChart.render();

            paymentMixChart = new ApexCharts(document.getElementById('payment_mix_chart'), {
                chart: { fontFamily: 'inherit', type: 'donut', height: 300 },
                series: [],
                noData: { text: 'Loading...' }
            });
            paymentMixChart.render();

            channelsChart = new ApexCharts(document.getElementById('channels_chart'), {
                chart: { fontFamily: 'inherit', type: 'donut', height: 300 },
                series: [],
                noData: { text: 'Loading...' }
            });
            channelsChart.render();

            discountsChart = new ApexCharts(document.getElementById('discounts_chart'), {
                chart: { fontFamily: 'inherit', type: 'bar', height: 300, toolbar: { show: false } },
                series: [],
                noData: { text: 'Loading...' }
            });
            discountsChart.render();

            function loadData() {
                var storeId = storeSelect.val();

                $.ajax({
                    url: "{{ route('reports.business_intelligence.data') }}",
                    data: { start_date: startDate, end_date: endDate, store_id: storeId },
                    success: function(response) {
                        renderSummary(response);
                        updateTrendChart(response.trend);
                        updatePaymentMix(response.payment_mix);
                        updateChannels(response.channels);
                        updateDiscounts(response.discounts);
                        renderTopItems(response.top_items);
                        renderTopCustomers(response.top_customers);
                    }
                });
            }

            function renderSummary(response) {
                var s = response.summary;

                $('#net_sales_value').text(fmtCurrency(s.net_sales));
                $('#net_sales_change').html(fmtChange(response.change_pct.net_sales));

                $('#gross_profit_value').text(fmtCurrency(s.gross_profit));
                $('#gross_margin_display').text(s.gross_margin_pct !== null ? '(' + s.gross_margin_pct.toFixed(1) + '% margin)' : '');

                $('#expenses_value').text(fmtCurrency(s.expenses_total));

                $('#net_profit_value').text(fmtCurrency(s.net_profit));
                $('#net_margin_display').text(s.net_margin_pct !== null ? '(' + s.net_margin_pct.toFixed(1) + '% margin)' : '');
                $('#net_profit_change').html(fmtChange(response.change_pct.net_profit));

                $('#data_through_badge').text('Data through: ' + (response.data_through ? moment(response.data_through).format('MMM D, YYYY') : 'no data yet'));
            }

            function updateTrendChart(trend) {
                var categories = trend.map(function(r) { return r.date; });

                trendChart.updateOptions({
                    series: [
                        { name: 'Net Sales', data: trend.map(function(r) { return r.net_sales; }) },
                        { name: 'Gross Profit', data: trend.map(function(r) { return r.gross_profit; }) },
                        { name: 'Expenses', data: trend.map(function(r) { return r.expenses; }) },
                        { name: 'Net Profit', data: trend.map(function(r) { return r.net_profit; }) }
                    ],
                    colors: [primaryColor, successColor, warningColor, infoColor],
                    stroke: { curve: 'smooth', width: [3, 3, 2, 3] },
                    dataLabels: { enabled: false },
                    xaxis: {
                        categories: categories,
                        type: 'datetime',
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                        labels: { style: { colors: labelColor, fontSize: '11px' } }
                    },
                    yaxis: {
                        labels: {
                            style: { colors: labelColor, fontSize: '12px' },
                            formatter: function(v) { return '₱' + numberWithCommas(Math.round(v)); }
                        }
                    },
                    tooltip: {
                        style: { fontSize: '12px' },
                        x: { format: 'MMM dd, yyyy' },
                        y: {
                            formatter: function(val) { return fmtCurrency(val); }
                        }
                    },
                    grid: {
                        borderColor: borderColor,
                        strokeDashArray: 4,
                        yaxis: { lines: { show: true } }
                    },
                    legend: { show: true },
                    noData: { text: 'No data available' }
                });
            }

            function updatePaymentMix(mix) {
                var labels = ['Cash', 'E-Wallet', 'Credit', 'Bank Transfer', 'Cheque'];
                var series = [mix.cash, mix.ewallet, mix.credit, mix.bank_transfer, mix.cheque];
                var hasData = series.some(function(v) { return v > 0; });

                paymentMixChart.updateOptions({
                    labels: labels,
                    series: hasData ? series : [],
                    colors: [primaryColor, successColor, warningColor, infoColor, dangerColor],
                    dataLabels: { enabled: true },
                    legend: { position: 'bottom' },
                    tooltip: {
                        y: {
                            formatter: function(val) { return fmtCurrency(val); }
                        }
                    },
                    noData: { text: 'No data available' }
                });
            }

            function updateChannels(channels) {
                var series = [channels.instore_sales, channels.ecommerce_sales];
                var hasData = series.some(function(v) { return v > 0; });

                channelsChart.updateOptions({
                    labels: ['In-Store', 'Ecommerce'],
                    series: hasData ? series : [],
                    colors: [primaryColor, successColor],
                    dataLabels: { enabled: true },
                    legend: { position: 'bottom' },
                    tooltip: {
                        y: {
                            formatter: function(val) { return fmtCurrency(val); }
                        }
                    },
                    noData: { text: 'No data available' }
                });
            }

            function updateDiscounts(discounts) {
                var rows = [
                    { label: 'Regular', value: discounts.regular },
                    { label: 'Senior Citizen', value: discounts.senior_citizen },
                    { label: 'PWD', value: discounts.pwd },
                    { label: 'Solo Parent', value: discounts.solo_parent },
                    { label: 'NAAC', value: discounts.naac },
                    { label: 'Voucher', value: discounts.voucher }
                ];
                var hasData = rows.some(function(r) { return r.value > 0; });

                discountsChart.updateOptions({
                    series: hasData ? [{ name: 'Discount', data: rows.map(function(r) { return r.value; }) }] : [],
                    colors: [warningColor],
                    plotOptions: {
                        bar: { horizontal: true, borderRadius: 5, barHeight: '50%' }
                    },
                    dataLabels: { enabled: false },
                    xaxis: {
                        categories: rows.map(function(r) { return r.label; }),
                        labels: {
                            style: { colors: labelColor, fontSize: '11px' },
                            formatter: function(v) { return '₱' + numberWithCommas(Math.round(v)); }
                        }
                    },
                    yaxis: {
                        labels: { style: { colors: labelColor, fontSize: '12px' } }
                    },
                    tooltip: {
                        y: {
                            formatter: function(val) { return fmtCurrency(val); }
                        }
                    },
                    grid: {
                        borderColor: borderColor,
                        strokeDashArray: 4
                    },
                    noData: { text: 'No data available' }
                });
            }

            function renderTopItems(items) {
                var body = '';
                if (items.length === 0) {
                    body = '<tr><td colspan="4" class="text-center text-muted py-5">No data available</td></tr>';
                } else {
                    items.forEach(function(r) {
                        body += '<tr>';
                        body += '<td class="text-gray-800 fw-semibold">' + $('<span>').text(r.name).html() + '</td>';
                        body += '<td class="text-end"><span class="badge badge-light-primary">' + numberWithCommas(r.qty_sold) + '</span></td>';
                        body += '<td class="fw-bold text-gray-900 text-end">' + fmtCurrency(r.revenue) + '</td>';
                        body += '<td class="text-end">' + fmtCurrency(r.profit) + '</td>';
                        body += '</tr>';
                    });
                }
                $('#top_items_table tbody').html(body);
            }

            function renderTopCustomers(customers) {
                var body = '';
                if (customers.length === 0) {
                    body = '<tr><td colspan="4" class="text-center text-muted py-5">No data available</td></tr>';
                } else {
                    customers.forEach(function(r) {
                        body += '<tr>';
                        body += '<td class="text-gray-800 fw-semibold">' + $('<span>').text(r.name).html() + '</td>';
                        body += '<td class="text-end"><span class="badge badge-light-primary">' + r.transactions + '</span></td>';
                        body += '<td class="fw-bold text-gray-900 text-end">' + fmtCurrency(r.spend_total) + '</td>';
                        body += '<td class="text-end">' + fmtCurrency(r.profit) + '</td>';
                        body += '</tr>';
                    });
                }
                $('#top_customers_table tbody').html(body);
            }

            loadData();

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
                loadData();
            });

            storeSelect.on('select2:select select2:clear', function() { loadData(); });

            $('#export_btn').on('click', function(e) {
                e.preventDefault();
                var params = new URLSearchParams({ start_date: startDate, end_date: endDate });
                var storeId = storeSelect.val();
                if (storeId) {
                    params.append('store_id', storeId);
                }
                window.location.href = "{{ route('reports.business_intelligence.export') }}?" + params.toString();
            });
        });
    </script>
@endsection
