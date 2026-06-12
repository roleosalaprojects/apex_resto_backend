@extends('layout.app')
@section('header')
    - AI Item Insights
@endsection
@section('title')
    AI Item Insights
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">AI Item Insights</li>
@endsection
@section('actions')
    <x-data-table.actions :show-export="false">
        <!--begin::Header-->
        <div class="px-7 py-5">
            <div class="fs-5 text-dark fw-bold">Filter Options</div>
        </div>
        <!--end::Header-->
        <div class="separator border-gray-200"></div>
        <!--begin::Form-->
        <div class="px-7 py-5">
            <!--begin::Store Select-->
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
            <!--end::Store Select-->
            <!--begin::Date Picker-->
            <div class="mb-3">
                <label class="form-label fw-semibold">Date:</label>
                <div>
                    <input type="text" class="form-control form-control-solid" id="date_picker" placeholder="Select date" value="{{ now()->format('Y-m-d') }}" />
                </div>
            </div>
            <!--end::Date Picker-->
        </div>
        <div class="separator border-gray-200"></div>
        <div class="px-7 py-5">
            <button type="button" class="btn btn-primary w-100" id="refresh_btn">
                <i class="ki-outline ki-arrows-circle fs-4"></i> Generate Insights
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
                    {{ $aiProvider }} is connected. Per-item AI insights will be generated with the rankings.
                @else
                    No AI provider available. Rankings will still work, but per-item AI insights won't be available.
                @endif
            </span>
        </div>
    </div>

    {{-- Statistics Cards --}}
    <div class="row g-5 g-xl-8 mb-7">
        <div class="col-xl-3">
            <div class="card bg-light-primary hoverable card-xl-stretch mb-xl-8">
                <div class="card-body">
                    <i class="ki-outline ki-crown fs-2hx text-primary ms-n1"></i>
                    <div class="text-gray-900 fw-bold fs-2 mt-5" id="stat_top_item">Loading...</div>
                    <div class="fw-semibold text-gray-600 fs-3">Top Item <span class="text-primary" id="stat_top_score"></span></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card bg-light-success hoverable card-xl-stretch mb-xl-8">
                <div class="card-body">
                    <i class="ki-outline ki-chart-line-up fs-2hx text-success ms-n1"></i>
                    <div class="text-gray-900 fw-bold fs-2 mt-5" id="stat_avg_score">-</div>
                    <div class="fw-semibold text-gray-600 fs-3">Avg Score</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card bg-light-info hoverable card-xl-stretch mb-xl-8">
                <div class="card-body">
                    <i class="ki-outline ki-category fs-2hx text-info ms-n1"></i>
                    <div class="text-gray-900 fw-bold fs-2 mt-5" id="stat_categories">-</div>
                    <div class="fw-semibold text-gray-600 fs-3">Categories</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card bg-light-danger hoverable card-xl-stretch mb-xl-8">
                <div class="card-body">
                    <i class="ki-outline ki-notification-bing fs-2hx text-danger ms-n1"></i>
                    <div class="text-gray-900 fw-bold fs-2 mt-5" id="stat_low_stock">-</div>
                    <div class="fw-semibold text-gray-600 fs-3">Low Stock Alerts</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Item Insights Table --}}
    <div class="card card-flush">
        <div class="card-header">
            <h3 class="card-title">Top 100 Sellable Items</h3>
            <div class="card-toolbar">
                <span class="text-muted fs-7" id="insights_date_label"></span>
            </div>
        </div>
        <div class="card-body">
            <x-data-table.table table-id="insights_table">
                <th width="50">Rank</th>
                <th>Item Name</th>
                <th>Category</th>
                <th width="200">Score</th>
                <th>Predicted Qty</th>
                <th>Stock</th>
                <th>Margin %</th>
                <th width="200">Factors</th>
                <th width="250">AI Insight</th>
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
            var datePicker = $('#date_picker');
            var refreshBtn = $('#refresh_btn');

            // Initialize Select2
            storeSelect.select2({
                dropdownParent: $("#datatables_menu")
            });

            // Initialize Flatpickr date picker
            var fp = flatpickr('#date_picker', {
                dateFormat: 'Y-m-d',
                defaultDate: '{{ now()->format("Y-m-d") }}',
                maxDate: 'today',
                onChange: function() {
                    loadData();
                    loadSummary();
                }
            });

            // DataTable
            var insightsTable = $('#insights_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("insights.data") }}',
                    data: function(d) {
                        d.store_id = storeSelect.val();
                        d.date = datePicker.val();
                    }
                },
                columns: [
                    { data: 'rank', className: 'fw-bold text-center' },
                    { data: 'item_name' },
                    { data: 'category_name', defaultContent: '<span class="text-muted">—</span>' },
                    { data: 'score_bar' },
                    { data: 'predicted_qty', render: function(d) { return parseFloat(d).toFixed(1); } },
                    {
                        data: 'current_stock',
                        render: function(d) {
                            if (d === null) return '<span class="text-muted">—</span>';
                            var val = parseFloat(d);
                            var cls = val <= 0 ? 'text-danger fw-bold' : (val < 10 ? 'text-warning' : '');
                            return '<span class="' + cls + '">' + val.toFixed(1) + '</span>';
                        }
                    },
                    {
                        data: 'profit_margin',
                        render: function(d) {
                            if (d === null) return '<span class="text-muted">—</span>';
                            return parseFloat(d).toFixed(1) + '%';
                        }
                    },
                    { data: 'factor_badges' },
                    { data: 'insight_text' }
                ],
                order: [[0, 'asc']],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100]
            });

            // Load summary stats
            function loadSummary() {
                $.get('{{ route("insights.summary") }}', {
                    store_id: storeSelect.val(),
                    date: datePicker.val()
                }, function(response) {
                    $('#stat_top_item').text(response.top_item || 'No data');
                    $('#stat_top_score').text(response.top_score ? '(' + response.top_score + ')' : '');
                    $('#stat_avg_score').text(response.avg_score || '-');
                    $('#stat_categories').text(response.categories_count || '-');
                    $('#stat_low_stock').text(response.low_stock_count || '0');
                    $('#insights_date_label').text(datePicker.val());
                });
            }

            // Reload table
            function loadData() {
                insightsTable.ajax.reload();
            }

            // Initial load
            loadSummary();

            // Refresh button — forces regeneration
            refreshBtn.on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Generating...');

                $.get('{{ route("insights.data") }}', {
                    store_id: storeSelect.val(),
                    date: datePicker.val(),
                    refresh: 1,
                    length: 1
                }, function() {
                    insightsTable.ajax.reload();
                    loadSummary();
                    btn.prop('disabled', false).html('<i class="ki-outline ki-arrows-circle fs-4"></i> Generate Insights');
                }).fail(function() {
                    btn.prop('disabled', false).html('<i class="ki-outline ki-arrows-circle fs-4"></i> Generate Insights');
                });
            });

            // Store filter change
            storeSelect.on('change', function() {
                loadData();
                loadSummary();
            });
        });
    </script>
@endsection
