@extends('layout.app')
@section('header')
    - Shop Visitors
@endsection
@section('title')
    Shop Visitor Analytics
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">Shop Visitors</li>
@endsection
@section('actions')
    <x-data-table.actions :show-export="false">
        <div class="px-7 py-5">
            <div class="fs-5 text-dark fw-bold">Filter Options</div>
        </div>
        <div class="separator border-gray-200"></div>
        <div class="px-7 py-5">
            <div class="mb-3">
                <label class="form-label fw-semibold">Date Range:</label>
                <input type="text" class="form-control form-control-solid" id="date_range" placeholder="Select dates" />
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Device Type:</label>
                <select class="form-select form-select-solid" id="device_filter">
                    <option value="">All Devices</option>
                    <option value="desktop">Desktop</option>
                    <option value="mobile">Mobile</option>
                    <option value="tablet">Tablet</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Page Type:</label>
                <select class="form-select form-select-solid" id="page_filter">
                    <option value="">All Pages</option>
                    <option value="browse">Browse</option>
                    <option value="product">Product</option>
                    <option value="cart">Cart</option>
                    <option value="checkout">Checkout</option>
                </select>
            </div>
        </div>
        <div class="separator border-gray-200"></div>
        <div class="px-7 py-5">
            <button type="button" class="btn btn-primary w-100 mb-2" id="apply_filter">
                <i class="ki-outline ki-filter fs-4"></i> Apply Filter
            </button>
            <a href="{{ route('analytics.visitors.export') }}" class="btn btn-light-success w-100" id="export_btn">
                <i class="ki-outline ki-file-down fs-4"></i> Export CSV
            </a>
        </div>
    </x-data-table.actions>
@endsection
@section('content')
    {{-- Statistics Cards --}}
    <div class="row g-5 g-xl-8 mb-7">
        <div class="col-xl-4">
            <div class="card card-flush h-xl-100">
                <div class="card-header pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-dark">Today</span>
                    </h3>
                </div>
                <div class="card-body pt-0">
                    <div class="d-flex flex-stack">
                        <div class="text-gray-700 fw-semibold fs-6 me-2">Total Visits</div>
                        <div class="d-flex align-items-senter">
                            <span class="text-gray-900 fw-bolder fs-2">{{ number_format($stats['today']['visits']) }}</span>
                        </div>
                    </div>
                    <div class="separator separator-dashed my-3"></div>
                    <div class="d-flex flex-stack">
                        <div class="text-gray-700 fw-semibold fs-6 me-2">Unique Visitors</div>
                        <div class="d-flex align-items-senter">
                            <span class="text-gray-900 fw-bolder fs-2">{{ number_format($stats['today']['unique']) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card card-flush h-xl-100">
                <div class="card-header pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-dark">This Week</span>
                    </h3>
                </div>
                <div class="card-body pt-0">
                    <div class="d-flex flex-stack">
                        <div class="text-gray-700 fw-semibold fs-6 me-2">Total Visits</div>
                        <div class="d-flex align-items-senter">
                            <span class="text-gray-900 fw-bolder fs-2">{{ number_format($stats['week']['visits']) }}</span>
                        </div>
                    </div>
                    <div class="separator separator-dashed my-3"></div>
                    <div class="d-flex flex-stack">
                        <div class="text-gray-700 fw-semibold fs-6 me-2">Unique Visitors</div>
                        <div class="d-flex align-items-senter">
                            <span class="text-gray-900 fw-bolder fs-2">{{ number_format($stats['week']['unique']) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card card-flush h-xl-100">
                <div class="card-header pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-dark">This Month</span>
                    </h3>
                </div>
                <div class="card-body pt-0">
                    <div class="d-flex flex-stack">
                        <div class="text-gray-700 fw-semibold fs-6 me-2">Total Visits</div>
                        <div class="d-flex align-items-senter">
                            <span class="text-gray-900 fw-bolder fs-2">{{ number_format($stats['month']['visits']) }}</span>
                        </div>
                    </div>
                    <div class="separator separator-dashed my-3"></div>
                    <div class="d-flex flex-stack">
                        <div class="text-gray-700 fw-semibold fs-6 me-2">Unique Visitors</div>
                        <div class="d-flex align-items-senter">
                            <span class="text-gray-900 fw-bolder fs-2">{{ number_format($stats['month']['unique']) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts Row --}}
    <div class="row g-5 g-xl-8 mb-7">
        <div class="col-xl-8">
            <div class="card card-flush h-xl-100">
                <div class="card-header pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-dark">Visits Over Time</span>
                    </h3>
                    <div class="card-toolbar">
                        <select class="form-select form-select-sm form-select-solid" id="days_select">
                            <option value="7" selected>Last 7 Days</option>
                            <option value="14">Last 14 Days</option>
                            <option value="30">Last 30 Days</option>
                        </select>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <canvas id="visitsChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card card-flush h-xl-100">
                <div class="card-header pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-dark">Device Breakdown</span>
                    </h3>
                </div>
                <div class="card-body pt-0">
                    <canvas id="deviceChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Second Charts Row --}}
    <div class="row g-5 g-xl-8 mb-7">
        <div class="col-xl-6">
            <div class="card card-flush h-xl-100">
                <div class="card-header pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-dark">Top Pages</span>
                    </h3>
                </div>
                <div class="card-body pt-0">
                    <div id="topPagesTable"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card card-flush h-xl-100">
                <div class="card-header pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-dark">Top Referrers</span>
                    </h3>
                </div>
                <div class="card-body pt-0">
                    <div id="topReferrersTable"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Visitors Table --}}
    <div class="card card-flush">
        <div class="card-header pt-5">
            <h3 class="card-title align-items-start flex-column">
                <span class="card-label fw-bold text-dark">Recent Visitors</span>
                <span class="text-gray-400 mt-1 fw-semibold fs-6">Last 500 visits</span>
            </h3>
        </div>
        <div class="card-body pt-0">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="visitors_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                        <th>Visitor</th>
                        <th>Page</th>
                        <th>Device</th>
                        <th>Browser</th>
                        <th>Referrer</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody class="fw-semibold text-gray-600"></tbody>
            </table>
        </div>
    </div>
@endsection

@section('vendor-scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection
@section('scripts')
<script>
    let visitsChart, deviceChart;

    $(document).ready(function() {
        // Initialize date range picker
        $('#date_range').daterangepicker({
            startDate: moment().subtract(6, 'days'),
            endDate: moment(),
            ranges: {
               'Today': [moment(), moment()],
               'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
               'Last 7 Days': [moment().subtract(6, 'days'), moment()],
               'Last 30 Days': [moment().subtract(29, 'days'), moment()],
               'This Month': [moment().startOf('month'), moment().endOf('month')],
            }
        });

        // Load charts
        loadCharts(7);

        // Load table
        loadTable();

        // Days selector
        $('#days_select').on('change', function() {
            loadCharts($(this).val());
        });

        // Apply filter
        $('#apply_filter').on('click', function() {
            loadTable();
        });

        // Export button
        $('#export_btn').on('click', function(e) {
            e.preventDefault();
            const dates = $('#date_range').data('daterangepicker');
            const from = dates.startDate.format('YYYY-MM-DD');
            const to = dates.endDate.format('YYYY-MM-DD');
            window.location.href = `{{ route('analytics.visitors.export') }}?date_from=${from}&date_to=${to}`;
        });
    });

    function loadCharts(days) {
        $.get('{{ route('analytics.visitors.charts') }}', { days: days }, function(data) {
            // Visits Chart
            const ctx = document.getElementById('visitsChart').getContext('2d');
            if (visitsChart) visitsChart.destroy();

            visitsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.visits_per_day.map(d => d.date),
                    datasets: [
                        {
                            label: 'Total Visits',
                            data: data.visits_per_day.map(d => d.visits),
                            borderColor: '#009EF7',
                            backgroundColor: 'rgba(0, 158, 247, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Unique Visitors',
                            data: data.visits_per_day.map(d => d.unique_visitors),
                            borderColor: '#50CD89',
                            backgroundColor: 'rgba(80, 205, 137, 0.1)',
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });

            // Device Chart
            const ctx2 = document.getElementById('deviceChart').getContext('2d');
            if (deviceChart) deviceChart.destroy();

            deviceChart = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: data.device_breakdown.map(d => d.device_type || 'Unknown'),
                    datasets: [{
                        data: data.device_breakdown.map(d => d.count),
                        backgroundColor: ['#009EF7', '#50CD89', '#FFC700', '#F1416C']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });

            // Top Pages Table
            let pagesHtml = '<table class="table table-row-dashed table-row-gray-300 gy-3">';
            pagesHtml += '<thead><tr><th>Page</th><th class="text-end">Views</th></tr></thead><tbody>';
            data.top_pages.forEach(p => {
                pagesHtml += `<tr><td class="text-truncate" style="max-width: 200px;">${p.page_visited}</td><td class="text-end">${p.views}</td></tr>`;
            });
            pagesHtml += '</tbody></table>';
            $('#topPagesTable').html(pagesHtml);

            // Top Referrers Table
            let refsHtml = '<table class="table table-row-dashed table-row-gray-300 gy-3">';
            refsHtml += '<thead><tr><th>Referrer</th><th class="text-end">Visits</th></tr></thead><tbody>';
            if (data.top_referrers.length === 0) {
                refsHtml += '<tr><td colspan="2" class="text-center text-muted">No referrers tracked</td></tr>';
            } else {
                data.top_referrers.forEach(r => {
                    refsHtml += `<tr><td>${r.referrer_domain}</td><td class="text-end">${r.count}</td></tr>`;
                });
            }
            refsHtml += '</tbody></table>';
            $('#topReferrersTable').html(refsHtml);
        });
    }

    function loadTable() {
        const dates = $('#date_range').data('daterangepicker');
        const params = {
            date_from: dates.startDate.format('YYYY-MM-DD'),
            date_to: dates.endDate.format('YYYY-MM-DD'),
            device_type: $('#device_filter').val(),
            page_type: $('#page_filter').val()
        };

        $.get('{{ route('analytics.visitors.data') }}', params, function(response) {
            const tbody = $('#visitors_table tbody');
            tbody.empty();

            response.data.forEach(v => {
                tbody.append(`
                    <tr>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="text-gray-800">${v.customer}</span>
                                <span class="text-gray-400 fs-7">${v.visitor_id}</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-light-primary">${v.page_type}</span>
                            <div class="text-gray-600 fs-7 text-truncate" style="max-width: 200px;">${v.page}</div>
                        </td>
                        <td>
                            <span class="badge badge-light-${v.device === 'mobile' ? 'warning' : v.device === 'tablet' ? 'info' : 'success'}">${v.device || '-'}</span>
                        </td>
                        <td>
                            <div>${v.browser || '-'}</div>
                            <span class="text-gray-400 fs-7">${v.platform || ''}</span>
                        </td>
                        <td>${v.referrer || '<span class="text-muted">Direct</span>'}</td>
                        <td>${v.created_at}</td>
                    </tr>
                `);
            });
        });
    }
</script>
@endsection
