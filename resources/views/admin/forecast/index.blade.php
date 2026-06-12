@extends('layout.app')
@section('header')
    - Demand Forecasting
@endsection
@section('title')
    Demand Forecasting
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">Demand Forecasting</li>
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
                <label class="form-label fw-semibold">Select Store:</label>
                <div>
                    <select class="form-select form-select-solid" id="store_select" data-kt-select2="true" data-placeholder="All Stores" data-allow-clear="true">
                        <option value="">All Stores</option>
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="mb-3">
                <label class="form-label fw-semibold">Forecast Days:</label>
                <div>
                    <select class="form-select form-select-solid" id="days_select">
                        <option value="7" selected>7 Days</option>
                        <option value="14">14 Days</option>
                        <option value="21">21 Days</option>
                        <option value="30">30 Days</option>
                    </select>
                </div>
            </div>
            <!--end::Input group-->
        </div>
        <!--begin::Menu separator-->
        <div class="separator border-gray-200"></div>
        <!--end::Menu separator-->
        <div class="px-7 py-5">
            <button type="button" class="btn btn-primary w-100" id="refresh_btn">
                <i class="ki-outline ki-arrows-circle fs-4"></i> Refresh Forecast
            </button>
        </div>
        <!--end::Form-->
    </x-data-table.actions>
@endsection
@section('content')
    {{-- AI Status Banner --}}
    <div class="alert alert-dismissible bg-light-{{ $aiStatus ? 'success' : 'warning' }} d-flex flex-column flex-sm-row p-5 mb-7">
        <i class="ki-outline ki-{{ $aiStatus ? 'check-circle' : 'information-5' }} fs-2hx text-{{ $aiStatus ? 'success' : 'warning' }} me-4 mb-5 mb-sm-0"></i>
        <div class="d-flex flex-column pe-0 pe-sm-10">
            <h4 class="fw-semibold">AI Insights {{ $aiStatus ? 'Enabled' : 'Unavailable' }}</h4>
            <span>
                @if($aiStatus)
                    {{ $aiProvider }} is connected. AI-powered insights and recommendations are available.
                @else
                    No AI provider available. Configure Anthropic API key or start Ollama for AI insights.
                @endif
            </span>
        </div>
    </div>

    {{-- Statistics Cards --}}
    <div class="row g-5 g-xl-8 mb-7">
        <div class="col-xl-3">
            <div class="card bg-light-primary hoverable card-xl-stretch mb-xl-8">
                <div class="card-body">
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17.8 8.79999L13 13.6L9.7 10.3C9.3 9.89999 8.7 9.89999 8.3 10.3L2.3 16.3C1.9 16.7 1.9 17.3 2.3 17.7C2.5 17.9 2.7 18 3 18C3.3 18 3.5 17.9 3.7 17.7L9 12.4L12.3 15.7C12.7 16.1 13.3 16.1 13.7 15.7L19.2 10.2L17.8 8.79999Z" fill="currentColor"/>
                            <path opacity="0.3" d="M22 13.1V7C22 6.4 21.6 6 21 6H14.9L22 13.1Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-8" id="total_predicted">Loading...</div>
                    <div class="fw-semibold text-gray-600 fs-3">Predicted Sales</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card bg-light-success hoverable card-xl-stretch mb-xl-8">
                <div class="card-body">
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13 5.91517C15.8 6.41517 18 8.81519 18 11.8152C18 12.5152 17.9 13.2152 17.6 13.9152L20.1 15.3152C20.6 15.6152 21.4 15.4152 21.6 14.8152C21.9 13.9152 22.1 12.9152 22.1 11.8152C22.1 7.01519 18.8 3.11521 14.3 2.01521C13.7 1.91521 13.1 2.31521 13.1 3.01521V5.91517H13Z" fill="currentColor"/>
                            <path opacity="0.3" d="M19.1 17.0152C19.7 17.3152 19.8 18.1152 19.3 18.5152C17.5 20.5152 14.9 21.7152 12 21.7152C9.1 21.7152 6.50001 20.5152 4.70001 18.5152C4.30001 18.0152 4.39999 17.3152 4.89999 17.0152L7.39999 15.6152C8.49999 16.9152 10.2 17.8152 12 17.8152C13.8 17.8152 15.5 17.0152 16.6 15.6152L19.1 17.0152ZM6.39999 13.9151C6.19999 13.2151 6 12.5152 6 11.8152C6 8.81517 8.2 6.41515 11 5.91515V3.01519C11 2.41519 10.4 1.91519 9.79999 2.01519C5.29999 3.01519 2 7.01517 2 11.8152C2 12.8152 2.2 13.8152 2.5 14.8152C2.7 15.4152 3.4 15.7152 4 15.3152L6.39999 13.9151Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-8" id="avg_confidence">-</div>
                    <div class="fw-semibold text-gray-600 fs-3">Avg Confidence</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card bg-light-danger hoverable card-xl-stretch mb-xl-8">
                <div class="card-body">
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path opacity="0.3" d="M21.25 18.525L13.05 21.825C12.35 22.125 11.65 22.125 10.95 21.825L2.75 18.525C1.75 18.125 1.75 16.725 2.75 16.325L4.04999 15.825L10.25 18.325C10.85 18.525 11.45 18.625 12.05 18.625C12.65 18.625 13.25 18.525 13.85 18.325L20.05 15.825L21.35 16.325C22.35 16.725 22.35 18.125 21.25 18.525ZM13.05 16.425L21.25 13.125C22.25 12.725 22.25 11.325 21.25 10.925L13.05 7.625C12.35 7.325 11.65 7.325 10.95 7.625L2.75 10.925C1.75 11.325 1.75 12.725 2.75 13.125L10.95 16.425C11.65 16.725 12.45 16.725 13.05 16.425Z" fill="currentColor"/>
                            <path d="M11.05 11.025L2.84998 7.725C1.84998 7.325 1.84998 5.925 2.84998 5.525L11.05 2.225C11.75 1.925 12.45 1.925 13.15 2.225L21.35 5.525C22.35 5.925 22.35 7.325 21.35 7.725L13.05 11.025C12.45 11.325 11.65 11.325 11.05 11.025Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-8" id="critical_count">-</div>
                    <div class="fw-semibold text-gray-600 fs-3">Critical Reorders</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card bg-light-warning hoverable card-xl-stretch mb-xl-8">
                <div class="card-body">
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path opacity="0.3" d="M20 15H4C2.9 15 2 14.1 2 13V7C2 6.4 2.4 6 3 6H21C21.6 6 22 6.4 22 7V13C22 14.1 21.1 15 20 15ZM13 12H11C10.5 12 10 12.4 10 13V16C10 16.5 10.4 17 11 17H13C13.6 17 14 16.5 14 16V13C14 12.4 13.6 12 13 12Z" fill="currentColor"/>
                            <path d="M14 6V5H10V6H8V5C8 3.9 8.9 3 10 3H14C15.1 3 16 3.9 16 5V6H14ZM20 15H14V16C14 16.6 13.5 17 13 17H11C10.5 17 10 16.6 10 16V15H4C3.6 15 3.3 14.9 3 14.7V18C3 19.1 3.9 20 5 20H19C20.1 20 21 19.1 21 18V14.7C20.7 14.9 20.4 15 20 15Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <div class="text-gray-900 fw-bold fs-2 mt-8" id="total_reorders">-</div>
                    <div class="fw-semibold text-gray-600 fs-3">Total Reorders</div>
                </div>
            </div>
        </div>
    </div>

    {{-- AI Insight Card --}}
    <div class="card card-bordered mb-7" id="ai_insight_card" style="display: none;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="ki-outline ki-abstract-26 fs-2 text-primary me-2"></i>
                AI Sales Insight
            </h3>
        </div>
        <div class="card-body">
            <p class="text-gray-700 fs-5 mb-0" id="ai_insight_text"></p>
        </div>
    </div>

    {{-- Sales Forecast Chart --}}
    <div class="card card-bordered mb-7">
        <div class="card-header">
            <h3 class="card-title">Sales Forecast</h3>
        </div>
        <div class="card-body">
            <div id="forecast_chart" style="height: 400px;"></div>
        </div>
    </div>

    {{-- Day of Week Pattern Chart --}}
    <div class="card card-bordered mb-7">
        <div class="card-header">
            <h3 class="card-title">Sales by Day of Week</h3>
        </div>
        <div class="card-body">
            <div id="pattern_chart" style="height: 350px;"></div>
        </div>
    </div>

    {{-- Reorder Suggestions Table --}}
    <div class="card card-flush">
        <div class="card-header">
            <h3 class="card-title">Reorder Suggestions</h3>
            <div class="card-toolbar">
                <span class="badge badge-light-danger me-2" id="badge_critical">0 Critical</span>
                <span class="badge badge-light-warning me-2" id="badge_high">0 High</span>
                <span class="badge badge-light-info me-3" id="badge_medium">0 Medium</span>
                <div class="btn-group">
                    <a href="{{ route('forecast.export-reorder', ['format' => 'excel']) }}" class="btn btn-sm btn-light-success" id="export_excel_btn">
                        <i class="ki-outline ki-file-down fs-4"></i> Export Excel
                    </a>
                    <a href="{{ route('forecast.export-reorder', ['format' => 'csv']) }}" class="btn btn-sm btn-light-info" id="export_csv_btn">
                        <i class="ki-outline ki-file fs-4"></i> CSV
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <x-data-table.table table-id="reorder_table">
                <th>Item</th>
                <th>Store</th>
                <th>Current Stock</th>
                <th>Predicted Demand</th>
                <th>Days Left</th>
                <th>Suggested Qty</th>
                <th>Urgency</th>
                <th>Action</th>
            </x-data-table.table>
        </div>
    </div>
@endsection
@section('vendor-styles')
    <link rel="stylesheet" href="{{asset("assets/plugins/custom/datatables/datatables.bundle.css")}}">
@endsection
@section('vendor-scripts')
    <script src="{{asset("assets/plugins/custom/datatables/datatables.bundle.js")}}"></script>
@endsection
@section('scripts')
    <script>
        $(document).ready(function(){
            var storeSelect = $('#store_select');
            var daysSelect = $('#days_select');
            var refreshBtn = $('#refresh_btn');

            // Initialize Select2
            storeSelect.select2({
                dropdownParent: $("#datatables_menu")
            });

            // Chart colors
            var primaryColor = KTUtil.getCssVariableValue('--bs-primary');
            var successColor = KTUtil.getCssVariableValue('--bs-success');
            var warningColor = KTUtil.getCssVariableValue('--bs-warning');
            var dangerColor = KTUtil.getCssVariableValue('--bs-danger');
            var infoColor = KTUtil.getCssVariableValue('--bs-info');
            var labelColor = KTUtil.getCssVariableValue('--bs-gray-500');
            var borderColor = KTUtil.getCssVariableValue('--bs-gray-200');

            // Forecast Chart
            var forecastChartEl = document.getElementById('forecast_chart');
            var forecastChartOptions = {
                chart: {
                    type: 'area',
                    height: 400,
                    toolbar: { show: true }
                },
                series: [],
                xaxis: {
                    categories: [],
                    labels: { style: { colors: labelColor } }
                },
                yaxis: {
                    labels: {
                        style: { colors: labelColor },
                        formatter: function(val) { return '₱' + numberWithCommas(val.toFixed(0)); }
                    }
                },
                colors: [primaryColor, successColor],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.4,
                        opacityTo: 0.1
                    }
                },
                stroke: { curve: 'smooth', width: 2 },
                dataLabels: { enabled: false },
                tooltip: {
                    y: { formatter: function(val) { return '₱' + numberWithCommas(val.toFixed(2)); } }
                },
                grid: { borderColor: borderColor },
                noData: { text: 'Loading forecast data...' }
            };
            var forecastChart = new ApexCharts(forecastChartEl, forecastChartOptions);
            forecastChart.render();

            // Pattern Chart
            var patternChartEl = document.getElementById('pattern_chart');
            var patternChartOptions = {
                chart: {
                    type: 'bar',
                    height: 350,
                    toolbar: { show: false }
                },
                series: [],
                xaxis: {
                    categories: [],
                    labels: { style: { colors: labelColor } }
                },
                yaxis: [
                    {
                        title: { text: 'Avg Sales (₱)' },
                        labels: {
                            style: { colors: labelColor },
                            formatter: function(val) { return '₱' + numberWithCommas(val.toFixed(0)); }
                        }
                    },
                    {
                        opposite: true,
                        title: { text: 'Avg Transactions' },
                        labels: { style: { colors: labelColor } }
                    }
                ],
                colors: [primaryColor, infoColor],
                plotOptions: {
                    bar: { borderRadius: 4, columnWidth: '50%' }
                },
                dataLabels: { enabled: false },
                grid: { borderColor: borderColor },
                noData: { text: 'Loading pattern data...' }
            };
            var patternChart = new ApexCharts(patternChartEl, patternChartOptions);
            patternChart.render();

            // Reorder DataTable
            var reorderTable = $('#reorder_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("forecast.reorder-suggestions") }}',
                    data: function(d) {
                        d.store_id = storeSelect.val();
                    }
                },
                columns: [
                    { data: 'item_name' },
                    { data: 'store_name' },
                    { data: 'current_stock', render: function(d) { return numberWithCommas(parseFloat(d).toFixed(2)); } },
                    { data: 'predicted_demand', render: function(d) { return numberWithCommas(parseFloat(d).toFixed(2)); } },
                    { data: 'days_until_stockout' },
                    { data: 'suggested_quantity', render: function(d) { return numberWithCommas(parseFloat(d).toFixed(2)); } },
                    {
                        data: 'urgency_badge',
                        render: function(data, type, row) {
                            // For export, return plain text; for display, return badge HTML
                            if (type === 'export') {
                                return row.urgency_text;
                            }
                            return data;
                        }
                    },
                    { data: 'action' }
                ],
                order: [[6, 'asc']]
            });

            // Load Forecast Data
            function loadForecastData(refresh = false) {
                $.get('{{ route("forecast.daily-sales") }}', {
                    days: daysSelect.val(),
                    store_id: storeSelect.val(),
                    refresh: refresh ? 1 : 0
                }, function(response) {
                    // Update stats
                    $('#total_predicted').text('₱' + numberWithCommas(response.total_predicted.toFixed(2)));
                    $('#avg_confidence').text(response.avg_confidence + '%');

                    // Update chart
                    forecastChart.updateOptions({
                        xaxis: { categories: response.chart.categories },
                        series: [
                            { name: 'Predicted Sales', data: response.chart.predicted },
                            { name: 'Upper Bound', data: response.chart.upperBound }
                        ]
                    });

                    // Update AI insight
                    if (response.ai_insight) {
                        $('#ai_insight_text').text(response.ai_insight);
                        $('#ai_insight_card').show();
                    } else {
                        $('#ai_insight_card').hide();
                    }
                });
            }

            // Load Reorder Summary
            function loadReorderSummary() {
                $.get('{{ route("forecast.reorder-summary") }}', {
                    store_id: storeSelect.val()
                }, function(response) {
                    $('#critical_count').text(response.critical);
                    $('#total_reorders').text(response.total);
                    $('#badge_critical').text(response.critical + ' Critical');
                    $('#badge_high').text(response.high + ' High');
                    $('#badge_medium').text(response.medium + ' Medium');
                });
            }

            // Load Pattern Data
            function loadPatternData() {
                $.get('{{ route("forecast.patterns") }}', {
                    days: 30,
                    store_id: storeSelect.val()
                }, function(response) {
                    patternChart.updateOptions({
                        xaxis: { categories: response.day_of_week_chart.categories },
                        series: [
                            { name: 'Avg Sales', type: 'column', data: response.day_of_week_chart.sales },
                            { name: 'Avg Transactions', type: 'line', data: response.day_of_week_chart.transactions }
                        ]
                    });
                });
            }

            // Initial load
            loadForecastData();
            loadReorderSummary();
            loadPatternData();

            // Refresh button
            refreshBtn.on('click', function() {
                $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Refreshing...');
                loadForecastData(true);
                reorderTable.ajax.reload();
                loadReorderSummary();
                loadPatternData();
                setTimeout(function() {
                    refreshBtn.prop('disabled', false).html('<i class="ki-outline ki-arrows-circle fs-4"></i> Refresh Forecast');
                }, 2000);
            });

            // Filter changes
            storeSelect.on('change', function() {
                loadForecastData();
                reorderTable.ajax.reload();
                loadReorderSummary();
                loadPatternData();
                updateExportLinks();
            });

            // Update export links with current store filter
            function updateExportLinks() {
                var storeId = storeSelect.val();
                var baseExcelUrl = '{{ route("forecast.export-reorder", ["format" => "excel"]) }}';
                var baseCsvUrl = '{{ route("forecast.export-reorder", ["format" => "csv"]) }}';

                if (storeId) {
                    $('#export_excel_btn').attr('href', baseExcelUrl + '&store_id=' + storeId);
                    $('#export_csv_btn').attr('href', baseCsvUrl + '&store_id=' + storeId);
                } else {
                    $('#export_excel_btn').attr('href', baseExcelUrl);
                    $('#export_csv_btn').attr('href', baseCsvUrl);
                }
            }

            daysSelect.on('change', function() {
                loadForecastData();
            });

            // Acknowledge button
            $(document).on('click', '.acknowledge-btn', function() {
                var btn = $(this);
                var id = btn.data('id');
                btn.prop('disabled', true);

                $.post('{{ url("admin/forecast/acknowledge") }}/' + id, {
                    _token: '{{ csrf_token() }}'
                }, function() {
                    reorderTable.ajax.reload();
                    loadReorderSummary();
                });
            });

            function numberWithCommas(x) {
                return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }
        });
    </script>
@endsection
