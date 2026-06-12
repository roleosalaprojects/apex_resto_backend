@extends('layout.app')
@section('header')
    - Peak Hours Analysis
@endsection
@section('title')
    Peak Hours Analysis
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted"><span class="">Reports</span></li>
    <li class="breadcrumb-item text-muted">Peak Hours</li>
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
    </x-data-table.actions>
@endsection
@section('content')
    {{-- Summary Cards --}}
    <div class="row g-5 g-xl-8 mb-7">
        <div class="col-xl-4">
            <!--begin::Statistics Widget 5-->
            <div class="card bg-light-success hoverable card-xl-stretch mb-xl-8">
                <!--begin::Body-->
                <div class="card-body">
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 8H4C3.4 8 3 8.4 3 9V19C3 19.6 3.4 20 4 20H20C20.6 20 21 19.6 21 19V9C21 8.4 20.6 8 20 8ZM12 17C10.3 17 9 15.7 9 14C9 12.3 10.3 11 12 11C13.7 11 15 12.3 15 14C15 15.7 13.7 17 12 17Z" fill="currentColor"/>
                            <path opacity="0.3" d="M20 5H4C3.4 5 3 5.4 3 6V8H21V6C21 5.4 20.6 5 20 5Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-5" id="busiest_day">-</div>
                    <div class="fw-semibold text-gray-600 fs-7">Busiest Day (avg daily sales)</div>
                </div>
                <!--end::Body-->
            </div>
            <!--end::Statistics Widget 5-->
        </div>
        <div class="col-xl-4">
            <!--begin::Statistics Widget 5-->
            <div class="card bg-light-primary hoverable card-xl-stretch mb-xl-8">
                <!--begin::Body-->
                <div class="card-body">
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17.8 8.79999L13 13.6L9.7 10.3C9.3 9.89999 8.7 9.89999 8.3 10.3L2.3 16.3C1.9 16.7 1.9 17.3 2.3 17.7C2.5 17.9 2.7 18 3 18C3.3 18 3.5 17.9 3.7 17.7L9 12.4L12.3 15.7C12.7 16.1 13.3 16.1 13.7 15.7L19.2 10.2L17.8 8.79999Z" fill="currentColor"/>
                            <path opacity="0.3" d="M22 13.1V7C22 6.4 21.6 6 21 6H14.9L22 13.1Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-5" id="peak_hour_display">-</div>
                    <div class="fw-semibold text-gray-600 fs-7">Peak Hour (avg sales)</div>
                </div>
                <!--end::Body-->
            </div>
            <!--end::Statistics Widget 5-->
        </div>
        <div class="col-xl-4">
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
                    <div class="text-gray-900 fw-bold fs-2 mt-5" id="slow_hour_display">-</div>
                    <div class="fw-semibold text-gray-600 fs-7">Slowest Hour (avg sales)</div>
                </div>
                <!--end::Body-->
            </div>
            <!--end::Statistics Widget 5-->
        </div>
    </div>

    {{-- Heatmap --}}
    <div class="card card-bordered mb-7">
        <!--begin::Header-->
        <div class="card-header">
            <h3 class="card-title">Avg Sales Heatmap (Hour x Day of Week)</h3>
        </div>
        <!--end::Header-->
        <!--begin::Body-->
        <div class="card-body">
            <div id="heatmap_chart" style="height: 350px;"></div>
        </div>
        <!--end::Body-->
    </div>

    {{-- Receipts Heatmap --}}
    <div class="card card-bordered mb-7">
        <!--begin::Header-->
        <div class="card-header">
            <h3 class="card-title">Avg Receipts Heatmap (Hour x Day of Week)</h3>
        </div>
        <!--end::Header-->
        <!--begin::Body-->
        <div class="card-body">
            <div id="receipts_heatmap_chart" style="height: 350px;"></div>
        </div>
        <!--end::Body-->
    </div>

    {{-- Hourly Bar Chart --}}
    <div class="card card-bordered mb-7">
        <!--begin::Header-->
        <div class="card-header">
            <h3 class="card-title">Avg Hourly Breakdown</h3>
        </div>
        <!--end::Header-->
        <!--begin::Body-->
        <div class="card-body">
            <div id="hourly_chart" style="height: 400px;"></div>
        </div>
        <!--end::Body-->
    </div>

    {{-- Peak & Slow Hours Tables --}}
    <div class="row g-5 g-xl-8">
        <div class="col-xl-6">
            <div class="card card-bordered">
                <!--begin::Header-->
                <div class="card-header">
                    <h3 class="card-title">Top 5 Peak Hours</h3>
                </div>
                <!--end::Header-->
                <!--begin::Body-->
                <div class="card-body py-3">
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-300 align-middle gs-0 gy-3" id="peak_table">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>Day</th>
                                    <th>Hour</th>
                                    <th class="text-end">Avg Sales</th>
                                    <th class="text-end">Avg Receipts</th>
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
                    <h3 class="card-title">Top 5 Slow Hours</h3>
                </div>
                <!--end::Header-->
                <!--begin::Body-->
                <div class="card-body py-3">
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-300 align-middle gs-0 gy-3" id="slow_table">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>Day</th>
                                    <th>Hour</th>
                                    <th class="text-end">Avg Sales</th>
                                    <th class="text-end">Avg Receipts</th>
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
            var dayNames = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            var storeSelect = $('#store_select');
            var startDate = moment().subtract(29, 'days').format('YYYY-MM-DD');
            var endDate = moment().format('YYYY-MM-DD');
            var heatmapChart, receiptsHeatmapChart, hourlyChart;

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

            function formatHour(h) {
                if (h === 0) return '12 AM';
                if (h < 12) return h + ' AM';
                if (h === 12) return '12 PM';
                return (h - 12) + ' PM';
            }

            function numberWithCommas(x) {
                return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }

            function fmtCurrency(v) {
                return '₱ ' + numberWithCommas(parseFloat(v).toFixed(2));
            }

            // Initialize charts with noData message
            heatmapChart = new ApexCharts(document.getElementById('heatmap_chart'), {
                chart: { fontFamily: 'inherit', type: 'heatmap', height: 350, toolbar: { show: true } },
                series: [],
                noData: { text: 'Loading...' }
            });
            heatmapChart.render();

            receiptsHeatmapChart = new ApexCharts(document.getElementById('receipts_heatmap_chart'), {
                chart: { fontFamily: 'inherit', type: 'heatmap', height: 350, toolbar: { show: true } },
                series: [],
                noData: { text: 'Loading...' }
            });
            receiptsHeatmapChart.render();

            hourlyChart = new ApexCharts(document.getElementById('hourly_chart'), {
                chart: { fontFamily: 'inherit', type: 'bar', height: 400, toolbar: { show: true } },
                series: [],
                noData: { text: 'Loading...' }
            });
            hourlyChart.render();

            function loadData() {
                var storeId = storeSelect.val();

                $.ajax({
                    url: "{{ route('reports.peak_hours.data') }}",
                    data: { start_date: startDate, end_date: endDate, store_id: storeId },
                    success: function(response) {
                        updateHeatmap(response.heatmap);
                        updateReceiptsHeatmap(response.heatmap);
                        updateHourlyChart(response.heatmap);
                        renderSummary(response);
                        renderTables(response.peak_hours, response.slow_hours);
                    }
                });
            }

            function updateHeatmap(heatmap) {
                var series = [];
                for (var d = 7; d >= 1; d--) {
                    var data = [];
                    for (var h = 0; h < 24; h++) {
                        var point = heatmap.find(function(p) { return p.day === d && p.hour === h; });
                        data.push({ x: formatHour(h), y: point ? point.avg_sales : 0 });
                    }
                    series.push({ name: dayNames[d], data: data });
                }

                heatmapChart.updateOptions({
                    dataLabels: { enabled: false },
                    colors: ['#F6A623'],
                    plotOptions: {
                        heatmap: {
                            shadeIntensity: 0.5,
                            colorScale: {
                                ranges: [
                                    { from: 0, to: 0, color: KTUtil.getCssVariableValue('--bs-gray-100'), name: 'No Sales' }
                                ]
                            }
                        }
                    },
                    tooltip: {
                        style: { fontSize: '12px' },
                        y: {
                            formatter: function(val) { return fmtCurrency(val); }
                        }
                    },
                    series: series,
                    noData: { text: 'No data available' }
                });
            }

            function updateReceiptsHeatmap(heatmap) {
                var series = [];
                for (var d = 7; d >= 1; d--) {
                    var data = [];
                    for (var h = 0; h < 24; h++) {
                        var point = heatmap.find(function(p) { return p.day === d && p.hour === h; });
                        data.push({ x: formatHour(h), y: point ? point.avg_transactions : 0 });
                    }
                    series.push({ name: dayNames[d], data: data });
                }

                receiptsHeatmapChart.updateOptions({
                    dataLabels: { enabled: false },
                    colors: [successColor],
                    plotOptions: {
                        heatmap: {
                            shadeIntensity: 0.5,
                            colorScale: {
                                ranges: [
                                    { from: 0, to: 0, color: KTUtil.getCssVariableValue('--bs-gray-100'), name: 'No Receipts' }
                                ]
                            }
                        }
                    },
                    tooltip: {
                        style: { fontSize: '12px' },
                        y: {
                            formatter: function(val) { return val.toFixed(1) + ' receipts'; }
                        }
                    },
                    series: series,
                    noData: { text: 'No data available' }
                });
            }

            function updateHourlyChart(heatmap) {
                // Aggregate averages across days-of-week for each hour
                var hourData = {};
                for (var h = 0; h < 24; h++) {
                    hourData[h] = { sales: 0, transactions: 0 };
                }
                heatmap.forEach(function(p) {
                    hourData[p.hour].sales += p.avg_sales;
                    hourData[p.hour].transactions += p.avg_transactions;
                });

                var categories = [];
                var salesData = [];
                var transData = [];
                for (var h = 0; h < 24; h++) {
                    categories.push(formatHour(h));
                    salesData.push(Math.round(hourData[h].sales * 100) / 100);
                    transData.push(Math.round(hourData[h].transactions * 10) / 10);
                }

                hourlyChart.updateOptions({
                    series: [
                        { name: 'Avg Sales (₱)', type: 'bar', data: salesData },
                        { name: 'Avg Receipts', type: 'line', data: transData }
                    ],
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            borderRadius: 5,
                            columnWidth: ['40%']
                        }
                    },
                    dataLabels: { enabled: false },
                    xaxis: {
                        categories: categories,
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                        labels: {
                            rotate: -45,
                            rotateAlways: true,
                            style: { colors: labelColor, fontSize: '11px' }
                        }
                    },
                    yaxis: [
                        {
                            title: { text: 'Avg Sales (₱)' },
                            labels: {
                                style: { colors: labelColor, fontSize: '12px' },
                                formatter: function(v) { return '₱' + numberWithCommas(Math.round(v)); }
                            }
                        },
                        {
                            opposite: true,
                            title: { text: 'Avg Receipts' },
                            labels: {
                                style: { colors: labelColor, fontSize: '12px' },
                                formatter: function(v) { return v.toFixed(1); }
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
                                if (opts.seriesIndex === 0) return fmtCurrency(v);
                                return v.toFixed(1) + ' receipts';
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

            function renderSummary(response) {
                if (response.busiest_day && response.busiest_day.day_name) {
                    $('#busiest_day').text(response.busiest_day.day_name + ' (' + fmtCurrency(response.busiest_day.avg_daily_sales) + ')');
                } else {
                    $('#busiest_day').text('No data');
                }
                if (response.peak_hours && response.peak_hours.length > 0) {
                    var p = response.peak_hours[0];
                    $('#peak_hour_display').text(p.day_name + ' ' + formatHour(p.hour) + ' (' + fmtCurrency(p.avg_sales) + ')');
                } else {
                    $('#peak_hour_display').text('No data');
                }
                if (response.slow_hours && response.slow_hours.length > 0) {
                    var s = response.slow_hours[0];
                    $('#slow_hour_display').text(s.day_name + ' ' + formatHour(s.hour) + ' (' + fmtCurrency(s.avg_sales) + ')');
                } else {
                    $('#slow_hour_display').text('No data');
                }
            }

            function renderTables(peakHours, slowHours) {
                var peakBody = '';
                if (peakHours.length === 0) {
                    peakBody = '<tr><td colspan="4" class="text-center text-muted py-5">No data available</td></tr>';
                } else {
                    peakHours.forEach(function(r) {
                        peakBody += '<tr>';
                        peakBody += '<td class="text-gray-800 fw-semibold">' + r.day_name + '</td>';
                        peakBody += '<td class="text-gray-800">' + formatHour(r.hour) + '</td>';
                        peakBody += '<td class="fw-bold text-gray-900 text-end">' + fmtCurrency(r.avg_sales) + '</td>';
                        peakBody += '<td class="text-end"><span class="badge badge-light-primary">' + r.avg_transactions.toFixed(1) + '</span></td>';
                        peakBody += '</tr>';
                    });
                }
                $('#peak_table tbody').html(peakBody);

                var slowBody = '';
                if (slowHours.length === 0) {
                    slowBody = '<tr><td colspan="4" class="text-center text-muted py-5">No data available</td></tr>';
                } else {
                    slowHours.forEach(function(r) {
                        slowBody += '<tr>';
                        slowBody += '<td class="text-gray-800 fw-semibold">' + r.day_name + '</td>';
                        slowBody += '<td class="text-gray-800">' + formatHour(r.hour) + '</td>';
                        slowBody += '<td class="fw-bold text-gray-900 text-end">' + fmtCurrency(r.avg_sales) + '</td>';
                        slowBody += '<td class="text-end"><span class="badge badge-light-warning">' + r.avg_transactions.toFixed(1) + '</span></td>';
                        slowBody += '</tr>';
                    });
                }
                $('#slow_table tbody').html(slowBody);
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
        });
    </script>
@endsection
