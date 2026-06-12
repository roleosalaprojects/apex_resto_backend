@extends('layout.app')
@section('header')
    - Customer Intelligence
@endsection
@section('title')
    Customer Intelligence
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted"><span class="">Reports</span></li>
    <li class="breadcrumb-item text-muted">Customer Intelligence</li>
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
                <label class="form-label fw-semibold">RFM Window:</label>
                <!--end::Label-->
                <!--begin::Input-->
                <select class="form-select form-select-solid" id="window_select">
                    <option value="90">Last 90 days</option>
                    <option value="180">Last 180 days</option>
                    <option value="365" selected>Last 365 days</option>
                </select>
                <!--end::Input-->
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="mb-3">
                <!--begin::Label-->
                <label for="daterangepicker" class="form-label fw-semibold">Funnel Date Range:</label>
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
            <a href="{{ route('reports.customer_intelligence.export') }}" class="btn btn-light-success w-100" id="export_btn">
                <i class="ki-outline ki-file-down fs-4"></i> Export Segments CSV
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
                            <path opacity="0.3" d="M22 12C22 17.5 17.5 22 12 22C6.5 22 2 17.5 2 12C2 6.5 6.5 2 12 2C17.5 2 22 6.5 22 12ZM12 7C10.3 7 9 8.3 9 10C9 11.7 10.3 13 12 13C13.7 13 15 11.7 15 10C15 8.3 13.7 7 12 7Z" fill="currentColor"/>
                            <path d="M12 22C14.6 22 17 21 18.7 19.4C17.9 16.9 15.2 15 12 15C8.8 15 6.09999 16.9 5.29999 19.4C6.99999 21 9.4 22 12 22Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-5" id="analyzed_value">-</div>
                    <div class="fw-semibold text-gray-600 fs-7">Customers Analyzed</div>
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
                            <path d="M10.0813 3.7242C10.8849 2.16438 13.1151 2.16438 13.9187 3.7242V3.7242C14.4016 4.66147 15.4909 5.1127 16.4951 4.79139V4.79139C18.1663 4.25668 19.7433 5.83365 19.2086 7.50485V7.50485C18.8873 8.50905 19.3385 9.59842 20.2758 10.0813V10.0813C21.8356 10.8849 21.8356 13.1151 20.2758 13.9187V13.9187C19.3385 14.4016 18.8873 15.4909 19.2086 16.4951V16.4951C19.7433 18.1663 18.1663 19.7433 16.4951 19.2086V19.2086C15.4909 18.8873 14.4016 19.3385 13.9187 20.2758V20.2758C13.1151 21.8356 10.8849 21.8356 10.0813 20.2758V20.2758C9.59842 19.3385 8.50905 18.8873 7.50485 19.2086V19.2086C5.83365 19.7433 4.25668 18.1663 4.79139 16.4951V16.4951C5.1127 15.4909 4.66147 14.4016 3.7242 13.9187V13.9187C2.16438 13.1151 2.16438 10.8849 3.7242 10.0813V10.0813C4.66147 9.59842 5.1127 8.50905 4.79139 7.50485V7.50485C4.25668 5.83365 5.83365 4.25668 7.50485 4.79139V4.79139C8.50905 5.1127 9.59842 4.66147 10.0813 3.7242V3.7242Z" fill="currentColor"/>
                            <path d="M14.8563 9.1903C15.0606 8.94984 15.3771 8.9385 15.6175 9.14289C15.858 9.34728 15.8229 9.66433 15.6185 9.9048L11.863 14.6558C11.6554 14.9001 11.2876 14.9258 11.048 14.7128L8.47854 12.4288C8.24239 12.2188 8.22106 11.8573 8.43109 11.6211C8.64112 11.385 9.00262 11.3636 9.23877 11.5737L11.3727 13.4705L14.8563 9.1903Z" fill="white"/>
                        </svg>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-5" id="champions_value">-</div>
                    <div class="fw-semibold text-gray-600 fs-7">Champions</div>
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
                            <path opacity="0.3" d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" fill="currentColor"/>
                            <path d="M11 10.6V14.6C11 15.2 11.4 15.6 12 15.6C12.6 15.6 13 15.2 13 14.6V10.6C13 10 12.6 9.60002 12 9.60002C11.4 9.60002 11 10 11 10.6ZM12 18.6C12.6 18.6 13 18.2 13 17.6C13 17 12.6 16.6 12 16.6C11.4 16.6 11 17 11 17.6C11 18.2 11.4 18.6 12 18.6Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-5" id="at_risk_value">-</div>
                    <div class="fw-semibold text-gray-600 fs-7">At Risk</div>
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
                            <path opacity="0.3" d="M3 13V11C3 10.4 3.4 10 4 10H20C20.6 10 21 10.4 21 11V13C21 13.6 20.6 14 20 14H4C3.4 14 3 13.6 3 13Z" fill="currentColor"/>
                            <path d="M13 3.20001V10H11V3.20001C11 2.60001 11.4 2.20001 12 2.20001C12.6 2.20001 13 2.60001 13 3.20001ZM11 20.8C11 21.4 11.4 21.8 12 21.8C12.6 21.8 13 21.4 13 20.8V14H11V20.8Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-5" id="avg_clv_value">-</div>
                    <div class="fw-semibold text-gray-600 fs-7">Avg Lifetime Profit per Customer</div>
                </div>
                <!--end::Body-->
            </div>
            <!--end::Statistics Widget 5-->
        </div>
    </div>

    {{-- Segment Distribution + Funnel --}}
    <div class="row g-5 g-xl-8 mb-7">
        <div class="col-xl-6">
            <div class="card card-bordered card-xl-stretch">
                <!--begin::Header-->
                <div class="card-header">
                    <h3 class="card-title">Customer Segments <span class="text-muted fs-7 fw-normal ms-2" id="rfm_window_label"></span></h3>
                </div>
                <!--end::Header-->
                <!--begin::Body-->
                <div class="card-body">
                    <div id="segments_chart" style="height: 350px;"></div>
                </div>
                <!--end::Body-->
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card card-bordered card-xl-stretch">
                <!--begin::Header-->
                <div class="card-header">
                    <h3 class="card-title">Ecommerce Funnel <span class="text-muted fs-7 fw-normal ms-2" id="funnel_conversion_label"></span></h3>
                </div>
                <!--end::Header-->
                <!--begin::Body-->
                <div class="card-body">
                    <div id="funnel_chart" style="height: 350px;"></div>
                </div>
                <!--end::Body-->
            </div>
        </div>
    </div>

    {{-- Segments Table --}}
    <div class="card card-bordered mb-7">
        <!--begin::Header-->
        <div class="card-header">
            <h3 class="card-title">Segment Breakdown <span class="text-muted fs-7 fw-normal ms-2">click a row to see its customers</span></h3>
        </div>
        <!--end::Header-->
        <!--begin::Body-->
        <div class="card-body py-3">
            <div class="table-responsive">
                <table class="table table-row-bordered table-row-gray-300 align-middle gs-0 gy-3" id="segments_table">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th>Segment</th>
                            <th>Description</th>
                            <th class="text-end">Customers</th>
                            <th class="text-end">%</th>
                            <th class="text-end">Avg Days Since Purchase</th>
                            <th class="text-end">Avg Receipts</th>
                            <th class="text-end">Net Spend</th>
                            <th class="text-end">Lifetime Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="8" class="text-center text-muted py-5">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!--end::Body-->
    </div>

    {{-- Segment Drill-down --}}
    <div class="card card-bordered mb-7 d-none" id="drilldown_card">
        <!--begin::Header-->
        <div class="card-header">
            <h3 class="card-title"><span id="drilldown_title">Customers</span> <span class="text-muted fs-7 fw-normal ms-2">top 100 by net spend</span></h3>
        </div>
        <!--end::Header-->
        <!--begin::Body-->
        <div class="card-body py-3">
            <div class="table-responsive">
                <table class="table table-row-bordered table-row-gray-300 align-middle gs-0 gy-3" id="drilldown_table">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th>Customer</th>
                            <th class="text-end">Days Since Purchase</th>
                            <th class="text-end">Receipts</th>
                            <th class="text-end">Net Spend</th>
                            <th class="text-end">R / F / M</th>
                            <th class="text-end">Lifetime Profit</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <!--end::Body-->
    </div>

    {{-- Funnel Stage Table --}}
    <div class="card card-bordered">
        <!--begin::Header-->
        <div class="card-header">
            <h3 class="card-title">Funnel Stages</h3>
        </div>
        <!--end::Header-->
        <!--begin::Body-->
        <div class="card-body py-3">
            <div class="table-responsive">
                <table class="table table-row-bordered table-row-gray-300 align-middle gs-0 gy-3" id="funnel_table">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th>Stage</th>
                            <th class="text-end">Count</th>
                            <th class="text-end">% of Previous Stage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="3" class="text-center text-muted py-5">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!--end::Body-->
    </div>
@endsection
@section('scripts')
    <script>
        $(document).ready(function() {
            var startDate = moment().subtract(29, 'days').format('YYYY-MM-DD');
            var endDate = moment().format('YYYY-MM-DD');
            var segmentsChart, funnelChart;
            var segmentCustomers = {};

            var labelColor = KTUtil.getCssVariableValue('--bs-gray-500');
            var borderColor = KTUtil.getCssVariableValue('--bs-gray-200');
            var primaryColor = KTUtil.getCssVariableValue('--bs-primary');
            var successColor = KTUtil.getCssVariableValue('--bs-success');

            function numberWithCommas(x) {
                return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }

            function fmtCurrency(v) {
                return '₱ ' + numberWithCommas(parseFloat(v).toFixed(2));
            }

            function esc(text) {
                return $('<span>').text(text).html();
            }

            segmentsChart = new ApexCharts(document.getElementById('segments_chart'), {
                chart: { fontFamily: 'inherit', type: 'bar', height: 350, toolbar: { show: false } },
                series: [],
                noData: { text: 'Loading...' }
            });
            segmentsChart.render();

            funnelChart = new ApexCharts(document.getElementById('funnel_chart'), {
                chart: { fontFamily: 'inherit', type: 'bar', height: 350, toolbar: { show: false } },
                series: [],
                noData: { text: 'Loading...' }
            });
            funnelChart.render();

            function loadData() {
                $.ajax({
                    url: "{{ route('reports.customer_intelligence.data') }}",
                    data: {
                        window_days: $('#window_select').val(),
                        start_date: startDate,
                        end_date: endDate
                    },
                    success: function(response) {
                        renderSummary(response.rfm);
                        updateSegmentsChart(response.rfm.segments);
                        renderSegmentsTable(response.rfm.segments);
                        segmentCustomers = response.rfm.segment_customers || {};
                        $('#drilldown_card').addClass('d-none');
                        updateFunnelChart(response.funnel);
                        renderFunnelTable(response.funnel);
                    }
                });
            }

            function renderSummary(rfm) {
                $('#analyzed_value').text(numberWithCommas(rfm.totals.analyzed_customers));
                $('#champions_value').text(numberWithCommas(rfm.totals.champions));
                $('#at_risk_value').text(numberWithCommas(rfm.totals.at_risk));
                $('#avg_clv_value').text(rfm.totals.avg_lifetime_profit !== null ? fmtCurrency(rfm.totals.avg_lifetime_profit) : '-');
                $('#rfm_window_label').text('last ' + rfm.window_days + ' days, as of ' + moment(rfm.as_of).format('MMM D, YYYY'));
            }

            function updateSegmentsChart(segments) {
                var active = segments.filter(function(s) { return s.count > 0; });

                segmentsChart.updateOptions({
                    series: active.length ? [{ name: 'Customers', data: active.map(function(s) { return s.count; }) }] : [],
                    colors: [primaryColor],
                    plotOptions: {
                        bar: { horizontal: true, borderRadius: 5, barHeight: '60%' }
                    },
                    dataLabels: { enabled: true },
                    xaxis: {
                        categories: active.map(function(s) { return s.segment; }),
                        labels: { style: { colors: labelColor, fontSize: '11px' } }
                    },
                    yaxis: {
                        labels: { style: { colors: labelColor, fontSize: '12px' } }
                    },
                    tooltip: {
                        y: {
                            formatter: function(val) { return numberWithCommas(val) + ' customers'; }
                        }
                    },
                    grid: { borderColor: borderColor, strokeDashArray: 4 },
                    noData: { text: 'No data available' }
                });
            }

            function renderSegmentsTable(segments) {
                var body = '';
                segments.forEach(function(s) {
                    body += '<tr class="segment-row cursor-pointer" data-segment="' + esc(s.segment) + '">';
                    body += '<td class="text-gray-800 fw-bold">' + esc(s.segment) + '</td>';
                    body += '<td class="text-gray-600 fs-7">' + esc(s.description) + '</td>';
                    body += '<td class="text-end"><span class="badge badge-light-primary">' + numberWithCommas(s.count) + '</span></td>';
                    body += '<td class="text-end">' + s.pct.toFixed(1) + '%</td>';
                    body += '<td class="text-end">' + (s.avg_recency_days !== null ? s.avg_recency_days : '-') + '</td>';
                    body += '<td class="text-end">' + (s.avg_frequency !== null ? s.avg_frequency : '-') + '</td>';
                    body += '<td class="fw-bold text-gray-900 text-end">' + fmtCurrency(s.monetary_total) + '</td>';
                    body += '<td class="text-end">' + fmtCurrency(s.lifetime_profit_total) + '</td>';
                    body += '</tr>';
                });
                $('#segments_table tbody').html(body);
            }

            $(document).on('click', '.segment-row', function() {
                var segment = $(this).data('segment');
                var customers = segmentCustomers[segment] || [];

                $('#drilldown_title').text(segment + ' Customers');

                var body = '';
                if (customers.length === 0) {
                    body = '<tr><td colspan="6" class="text-center text-muted py-5">No customers in this segment</td></tr>';
                } else {
                    customers.forEach(function(c) {
                        body += '<tr>';
                        body += '<td class="text-gray-800 fw-semibold">' + esc(c.name) + '</td>';
                        body += '<td class="text-end">' + c.recency_days + '</td>';
                        body += '<td class="text-end"><span class="badge badge-light-primary">' + c.frequency + '</span></td>';
                        body += '<td class="fw-bold text-gray-900 text-end">' + fmtCurrency(c.monetary) + '</td>';
                        body += '<td class="text-end"><span class="badge badge-light">' + c.r + ' / ' + c.f + ' / ' + c.m + '</span></td>';
                        body += '<td class="text-end">' + fmtCurrency(c.lifetime_profit) + '</td>';
                        body += '</tr>';
                    });
                }
                $('#drilldown_table tbody').html(body);
                $('#drilldown_card').removeClass('d-none');
                $('#drilldown_card')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
            });

            function updateFunnelChart(funnel) {
                var hasData = funnel.stages.some(function(s) { return s.count > 0; });

                funnelChart.updateOptions({
                    series: hasData ? [{ name: 'Count', data: funnel.stages.map(function(s) { return s.count; }) }] : [],
                    colors: [successColor],
                    plotOptions: {
                        bar: { horizontal: true, borderRadius: 5, barHeight: '70%', isFunnel: true }
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: function(val, opt) {
                            return funnel.stages[opt.dataPointIndex].stage + ': ' + numberWithCommas(val);
                        }
                    },
                    xaxis: {
                        categories: funnel.stages.map(function(s) { return s.stage; })
                    },
                    tooltip: {
                        y: {
                            formatter: function(val) { return numberWithCommas(val); }
                        }
                    },
                    legend: { show: false },
                    noData: { text: 'No data available' }
                });

                $('#funnel_conversion_label').text(
                    funnel.overall_conversion_pct !== null
                        ? funnel.overall_conversion_pct + '% visitor-to-order conversion'
                        : ''
                );
            }

            function renderFunnelTable(funnel) {
                var body = '';
                funnel.stages.forEach(function(s) {
                    body += '<tr>';
                    body += '<td class="text-gray-800 fw-semibold">' + esc(s.stage) + '</td>';
                    body += '<td class="text-end fw-bold">' + numberWithCommas(s.count) + '</td>';
                    body += '<td class="text-end">' + (s.pct_of_previous !== null ? '<span class="badge badge-light-success">' + s.pct_of_previous + '%</span>' : '-') + '</td>';
                    body += '</tr>';
                });
                $('#funnel_table tbody').html(body);
            }

            loadData();

            $('#window_select').on('change', loadData);

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

            $('#export_btn').on('click', function(e) {
                e.preventDefault();
                var params = new URLSearchParams({ window_days: $('#window_select').val() });
                window.location.href = "{{ route('reports.customer_intelligence.export') }}?" + params.toString();
            });
        });
    </script>
@endsection
