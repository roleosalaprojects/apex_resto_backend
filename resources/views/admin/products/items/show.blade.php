@extends('layout.app')
@section('title')
    {{$item->name}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('items.index') }}">Items</a></li>
    <li class="breadcrumb-item text-muted">{{ $item->name }}</li>
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
                        <option data-select2-id="select2-data-12-htww"></option>
                    </select>
                </div>
                <!--end::Input-->
            </div>
            <!--end::Input group-->
        </div>
        <!--begin::Menu separator-->
        <div class="separator border-gray-200"></div>
        <!--end::Menu separator-->
        <div class="px-7 py-5">
            <div class="mb-5">
                <label for="daterangepicker" class="form-label">Date</label>
                <input class="form-control form-control-solid form-control" placeholder="Pick date range" id="daterangepicker"/>
            </div>
        </div>
        <!--end::Form-->
        <!--begin::Menu separator-->
        <div class="separator border-gray-200"></div>
        <!--end::Menu separator-->
    </x-data-table.actions>
    <x-general.search-table
        title="Date"
    ></x-general.search-table>
    <a href="{{route('items.edit', $item->id)}}" class="btn btn-info">Update</a>


@endsection
@section('content')
    @php
        $totalStock = 0;
        foreach($stocks as $stock){
            $totalStock += $stock->stock;
        }
    @endphp
    {{-- Product Overview & Stats --}}
    <div class="row g-5 mb-5">
        {{-- Product Info Card --}}
        <div class="col-lg-4">
            <div class="card card-flush h-100">
                <div class="card-header pt-5">
                    <div class="card-title d-flex flex-column">
                        <span class="fs-2hx fw-bold text-dark me-2 lh-1 ls-n2">₱ {{ number_format($item->price, 2) }}</span>
                        <span class="text-gray-500 pt-1 fw-semibold fs-6">Current Price</span>
                    </div>
                </div>
                <div class="card-body pt-2 pb-4">
                    <div class="d-flex flex-wrap gap-5">
                        <div class="d-flex flex-column flex-grow-1">
                            <span class="text-gray-500 fs-7">Cost</span>
                            <span class="fs-4 fw-bold text-gray-800">₱ {{ number_format($item->cost, 2) }}</span>
                        </div>
                        <div class="d-flex flex-column flex-grow-1">
                            <span class="text-gray-500 fs-7">Markup</span>
                            <span class="fs-4 fw-bold text-gray-800">{{ $item->markup }}%</span>
                        </div>
                        <div class="d-flex flex-column flex-grow-1">
                            <span class="text-gray-500 fs-7">Total Stock</span>
                            <span class="fs-4 fw-bold @if($totalStock < 0) text-danger @elseif($totalStock <= 50) text-warning @else text-success @endif">{{ number_format($totalStock, 2) }}</span>
                        </div>
                    </div>
                    <div class="separator separator-dashed my-4"></div>
                    <div class="d-flex flex-column">
                        <span class="text-gray-500 fs-7 mb-2">Stock by Location</span>
                        @foreach ($stocks as $stock)
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-gray-700 fs-7">{{ $stock->store }}</span>
                                <span class="badge @if($stock->stock < 0) badge-light-danger @elseif($stock->stock <= 50) badge-light-warning @else badge-light-success @endif">{{ number_format($stock->stock, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        {{-- Stats Cards --}}
        <div class="col-lg-8">
            <div class="row g-5 h-100">
                <div class="col-6 col-md-4">
                    <div class="card card-flush h-100">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <span class="fs-2hx fw-bold text-dark d-block" id="sales">₱ 0.00</span>
                            <span class="text-gray-500 fs-6 fw-semibold">Total Sales</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="card card-flush h-100">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <span class="fs-2hx fw-bold text-dark d-block" id="qty">0.00</span>
                            <span class="text-gray-500 fs-6 fw-semibold">Qty Sold</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="card card-flush h-100">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <span class="fs-2hx fw-bold text-success d-block" id="revenue">₱ 0.00</span>
                            <span class="text-gray-500 fs-6 fw-semibold">Revenue</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="card card-flush h-100">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <span class="fs-2hx fw-bold text-dark d-block" id="avg">₱ 0.00</span>
                            <span class="text-gray-500 fs-6 fw-semibold">Avg Price</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="card card-flush h-100">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <span class="fs-2hx fw-bold text-info d-block" id="min">₱ 0.00</span>
                            <span class="text-gray-500 fs-6 fw-semibold">Min Price</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="card card-flush h-100">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <span class="fs-2hx fw-bold text-warning d-block" id="max">₱ 0.00</span>
                            <span class="text-gray-500 fs-6 fw-semibold">Max Price</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sales Insight Chart & Sales Data Table --}}
    <div class="row g-5 mb-5">
        {{-- Sales Insight Chart --}}
        <div class="col-lg-6">
            <div class="card card-flush h-100">
                <div class="card-header pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-dark">Sales Insight</span>
                        <span class="text-gray-500 mt-1 fw-semibold fs-7">Sales performance over time</span>
                    </h3>
                </div>
                <div class="card-body pt-0">
                    <div id="insight_chart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
        {{-- Sales Data Table --}}
        <div class="col-lg-6">
            <div class="card card-flush h-100">
                <div class="card-header pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-dark">Sales Data</span>
                        <span class="text-gray-500 mt-1 fw-semibold fs-7">Detailed breakdown by period</span>
                    </h3>
                </div>
                <div class="card-body pt-0">
                    <div class="hover-scroll-overlay-y" style="max-height: 300px;">
                        <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-3" id="insight_table">
                            <thead class="bg-body">
                                <tr class="fw-bold text-muted">
                                    <th>Date</th>
                                    <th>Qty Sold</th>
                                    <th>Sub Total</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Margin Breakdown --}}
    @php
        $cost = (float) $item->cost;
        $price = (float) $item->price;
        $basePriceMargin = $cost > 0 ? (($price - $cost) / $cost) * 100 : null;
        $specialDiscounts = $item->discountable ? array_filter([
            'Senior Citizen' => (float) $item->senior,
            'PWD' => (float) $item->pwd,
            'Solo Parent' => (float) $item->solo_parent,
            'National Athletes & Coaches' => (float) $item->naac,
        ], fn ($pct) => $pct > 0) : [];
        $fmtPct = fn ($n) => ($n >= 0 ? '+' : '') . number_format($n, 1) . '%';
        $marginClass = fn ($n) => $n === null ? 'text-muted' : ($n >= 0 ? 'text-success' : 'text-danger');
    @endphp
    <div class="row g-5 mb-5">
        <div class="col-lg-12">
            <div class="card card-flush">
                <div class="card-header pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-dark">Margin Breakdown</span>
                        <span class="text-gray-500 mt-1 fw-semibold fs-7">Profit margin at every price point</span>
                    </h3>
                </div>
                <div class="card-body pt-0">
                    @if ($cost <= 0)
                        <div class="text-muted fs-7 py-5">Set a non-zero cost to see margins.</div>
                    @else
                        {{-- Base price row --}}
                        <div class="d-flex justify-content-between align-items-center py-3 border-bottom border-gray-200">
                            <div>
                                <div class="text-gray-800 fw-bold fs-6">Base Price</div>
                                <div class="text-gray-500 fs-7">vs ₱ {{ number_format($cost, 2) }} cost</div>
                            </div>
                            <div class="text-end">
                                <div class="text-gray-800 fw-bold fs-6">₱ {{ number_format($price, 2) }}</div>
                                <div class="fw-bold fs-7 {{ $marginClass($basePriceMargin) }}" data-test="base-margin">
                                    {{ $fmtPct($basePriceMargin) }}
                                </div>
                            </div>
                        </div>

                        {{-- Per-UoM margins --}}
                        @if ($item->itemUnits->isNotEmpty())
                            <div class="mt-5">
                                <div class="text-gray-500 fs-7 fw-semibold text-uppercase mb-2">Per Unit of Measure</div>
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-2 mb-0">
                                    <thead>
                                        <tr class="fw-bold text-muted fs-7">
                                            <th>Unit</th>
                                            <th>Qty</th>
                                            <th>Price</th>
                                            <th>Cost Basis</th>
                                            <th class="text-end">Margin</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($item->itemUnits as $unit)
                                            @php
                                                $basis = $cost * (float) $unit->qty;
                                                $unitMargin = $basis > 0 ? ((((float) $unit->price) - $basis) / $basis) * 100 : null;
                                            @endphp
                                            <tr>
                                                <td class="text-gray-800 fs-7">{{ $unit->unit?->name ?? '—' }}</td>
                                                <td class="text-gray-600 fs-7">{{ rtrim(rtrim(number_format((float) $unit->qty, 2, '.', ''), '0'), '.') ?: '0' }}</td>
                                                <td class="text-gray-800 fw-semibold fs-7">₱ {{ number_format((float) $unit->price, 2) }}</td>
                                                <td class="text-gray-600 fs-7">₱ {{ number_format($basis, 2) }}</td>
                                                <td class="text-end fw-bold fs-7 {{ $marginClass($unitMargin) }}">
                                                    {{ $unitMargin === null ? '—' : $fmtPct($unitMargin) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        {{-- Special discount margins --}}
                        @if (! empty($specialDiscounts))
                            <div class="mt-5">
                                <div class="text-gray-500 fs-7 fw-semibold text-uppercase mb-2">After Special Discount</div>
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-2 mb-0">
                                    <thead>
                                        <tr class="fw-bold text-muted fs-7">
                                            <th>Discount</th>
                                            <th>%</th>
                                            <th>Effective Price</th>
                                            <th class="text-end">Margin</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($specialDiscounts as $label => $pct)
                                            @php
                                                $effective = $price * (1 - $pct / 100);
                                                $specialMargin = (($effective - $cost) / $cost) * 100;
                                            @endphp
                                            <tr>
                                                <td class="text-gray-800 fs-7">{{ $label }}</td>
                                                <td class="text-gray-600 fs-7">{{ rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.') }}%</td>
                                                <td class="text-gray-800 fw-semibold fs-7">₱ {{ number_format($effective, 2) }}</td>
                                                <td class="text-end fw-bold fs-7 {{ $marginClass($specialMargin) }}">
                                                    {{ $fmtPct($specialMargin) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Wholesale Price Tiers --}}
    <div class="row g-5 mb-5">
        <div class="col-lg-12">
            <div class="card card-flush">
                <div class="card-header pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-dark">Volume Price Tiers</span>
                        <span class="text-gray-500 mt-1 fw-semibold fs-7">Quantity-based discount pricing</span>
                    </h3>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-sm btn-primary" id="btnAddTier">
                            <i class="fas fa-plus me-1"></i> Add Tier
                        </button>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-3" id="wholesale_tiers_table">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th>Min Qty</th>
                                <th>Discount</th>
                                <th>Effective Price</th>
                                <th>Margin</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <div id="noTiersMessage" class="text-center text-muted py-5 fs-7 d-none">No volume price tiers configured</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Wholesale Tier Modal --}}
    <div class="modal fade" id="tierModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tierModalTitle">Add Price Tier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="tierEditId">
                    <div class="form-group mb-5">
                        <label for="tierMinQty" class="form-label required">Minimum Quantity</label>
                        <input type="number" class="form-control" id="tierMinQty" min="1" step="1">
                        <span class="text-danger d-none" id="tierMinQtyError"></span>
                    </div>
                    <div class="form-group mb-5">
                        <label for="tierDiscount" class="form-label required">Discount Amount (₱)</label>
                        <input type="number" class="form-control" id="tierDiscount" min="0.01" step="0.01">
                        <span class="text-danger d-none" id="tierDiscountError"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btnSaveTier">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Price History Chart & Table --}}
    <div class="row g-5">
        {{-- Price History Chart --}}
        <div class="col-lg-6">
            <div class="card card-flush h-100">
                <div class="card-header pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-dark">Price History</span>
                        <span class="text-gray-500 mt-1 fw-semibold fs-7">Price changes over time</span>
                    </h3>
                    <div class="card-toolbar">
                        <input class="form-control form-control-solid form-control-sm w-200px" placeholder="Select dates" id="priceHistoryDatePicker"/>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div id="price_history_chart" style="height: 400px;"></div>
                </div>
            </div>
        </div>
        {{-- Price History Table --}}
        <div class="col-lg-6">
            <div class="card card-flush h-100">
                <div class="card-header pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold text-dark">Price Change Log</span>
                        <span class="text-gray-500 mt-1 fw-semibold fs-7">Detailed price change records</span>
                    </h3>
                </div>
                <div class="card-body pt-0">
                    <div class="hover-scroll-overlay-y" style="max-height: 400px;">
                        <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-3" id="price_history_table">
                            <thead class="sticky-top bg-body">
                                <tr class="fw-bold text-muted">
                                    <th>Date</th>
                                    <th>Old Price</th>
                                    <th>New Price</th>
                                    <th>Changed By</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('vendor-styles')
    <link rel="stylesheet" href="{{asset("assets/plugins/custom/datatables/datatables.bundle.css")}}">
@endsection
@section('vendor-scripts')
    {{-- DataTables --}}
    <script src="{{asset("assets/plugins/custom/datatables/datatables.bundle.js")}}"></script>
@endsection
@section('scripts')
    
<script>
    $(document).ready(function (){
        let startDate = moment().startOf('day');
        let endDate = moment().endOf('day');

        // Select2
        var store_select = $("#store_select");

        // Table
        let table = $("#insight_table")

        // Init Select2
        store_select.select2({
            dropdownParent: $("#datatables_menu"),
            ajax: {
                url: "{{ route('stores.select') }}",
                type: "GET",
                delay: 250,
                dataType: "JSON",
                data: function (params) {
                    var queryParameters = {
                        term: params.term
                    }
                    return queryParameters;
                },
                processResults: function (data) {
                    // console.log(data);
                    return {
                        results: data
                    };
                },
            }
        });

        // Chart
        var element = document.getElementById('insight_chart');

        var height = parseInt(KTUtil.css(element, 'height'));
        var labelColor = KTUtil.getCssVariableValue('--bs-gray-500');
        var borderColor = KTUtil.getCssVariableValue('--bs-gray-200');

        var baseColor = KTUtil.getCssVariableValue('--bs-primary');
        var salesColor = KTUtil.getCssVariableValue('--bs-success');

        // Chart Options
        var options = {
            chart: {
                fontFamily: 'inherit',
                stacked: true,
                height: height,
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    stacked: true,
                    horizontal: false,
                    borderRadius: 5,
                    borderRadiusApplication: 'around',
                    borderRadiusWhenStacked: 'first',
                    columnWidth: ['30%'],
                },
            },
            legend: {
                show: false
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth',
                show: true,
                width: 2,
                colors: ['transparent']
            },
            xaxis: {
                categories: [],
                axisBorder: {
                    show: false,
                },
                axisTicks: {
                    show: false
                },
                labels: {
                    style: {
                        colors: labelColor,
                        fontSize: '12px'
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: labelColor,
                        fontSize: '12px'
                    },
                    formatter: function(value){
                        return convertNumberShorter(value);
                    }
                }
            },
            fill: {
                opacity: 1
            },
            states: {
                normal: {
                    filter: {
                        type: 'none',
                        value: 0
                    }
                },
                hover: {
                    filter: {
                        type: 'none',
                        value: 0
                    }
                },
                active: {
                    allowMultipleDataPointsSelection: false,
                    filter: {
                        type: 'none',
                        value: 0
                    }
                }
            },
            tooltip: {
                style: {
                    fontSize: '12px'
                },
                y: {
                    formatter: function (val) {
                        return numberWithCommas(val)
                    }
                }
            },
            colors: [baseColor, salesColor],
            grid: {
                borderColor: borderColor,
                strokeDashArray: 4,
                yaxis: {
                    lines: {
                        show: true
                    }
                },
                padding: {
                    top: 0,
                    right: 0,
                    bottom: 0,
                    left: 0
                }
            },
            series: [],
            noData: {
                text: 'Loading...'
            }
        }

        var chart = new ApexCharts(element, options);
        chart.render();

        let tableOptions = {
            filter: true,
            responsive: true,
            serverside: true,
            processing: true,
            columns: [
                {'data': 'time'},
                {'data': 'qty'},
                {'data': 'sales'},
            ],
            columnDefs: [
                {
                    targets: 1,
                    render: function(data, type, full){
                        return numberWithCommas(full.qty.toFixed(2));
                    }
                },
                {
                    targets: 2,
                    render: function(data, type, full){
                        return "₱ " + numberWithCommas(full.sales.toFixed(2));
                    }
                },
            ],
            ajax: {
                type: 'get',
                data: {
                    'startDate': function () {
                        return startDate
                    },
                    'endDate': function () {
                        return endDate
                    },
                    'store_select': function () {
                        return store_select.val()
                    },
                },
                url: "{{ route('item.insight', $item->id) }}",
                dataSrc: function (response) {
                    (response.prices.total != null) ? $("#sales").text(`₱ ` + numberWithCommas((response.prices.total).toFixed(2))) : $("#sales").text(`₱ 0.00`);
                    (response.prices.qty != null) ? $("#qty").text(numberWithCommas((response.prices.qty).toFixed(2))) : $("#avg").text(`₱ 0.00`);
                    (response.prices.avg != null) ? $("#avg").text(`₱ ` + numberWithCommas((response.prices.avg).toFixed(2))) : $("#avg").text(`₱ 0.00`);
                    (response.prices.min != null) ? $("#min").text(`₱ ` + numberWithCommas((response.prices.min).toFixed(2))) : $("#min").text(`₱ 0.00`);
                    (response.prices.max != null) ? $("#max").text(`₱ ` + numberWithCommas((response.prices.max).toFixed(2))) : $("#max").text(`₱ 0.00`);
                    (response.prices.revenue != null) ? $("#revenue").text(`₱ ` + numberWithCommas((response.prices.revenue).toFixed(2))) : $("#revenue").text(`₱ 0.00`);
                    chart.updateOptions({
                        series: [
                            {
                                name: 'Products Sold',
                                type: 'bar',
                                stacked: true,
                                data: response.insight.original.data.map(function (val) {
                                    return val.qty.toFixed(2)
                                })
                            },
                            {
                                name: 'Sales',
                                type: 'bar',
                                // stacked: true,
                                data: response.insight.original.data.map(function (val) {
                                    return val.sales.toFixed(2)
                                })
                            },
                        ],
                        xaxis: {
                            categories: response.insight.original.data.map(function (e) {
                                return e.time
                            })
                        }
                    });
                    return response.insight.original.data;
                }
            }
        }

        table.DataTable(tableOptions);

        $('#daterangepicker').daterangepicker({
            'startDate': startDate,
            'endDate': endDate,
            showDropdowns: true,
            ranges: {
                "Today": [moment(), moment()],
                "Yesterday": [moment().subtract(1, "days"), moment().subtract(1, "days")],
                "Last 7 Days": [moment().subtract(6, "days"), moment()],
                "Last 30 Days": [moment().subtract(29, "days"), moment()],
                "This Month": [moment().startOf("month"), moment().endOf("month")],
                "This Year": [moment().startOf("year"), moment().endOf("year")],
                "Last Month": [moment().subtract(1, "month").startOf("month"), moment().subtract(1, "month").endOf("month")],
                "Last 6 Months": [moment().subtract(6, "month").startOf("month"), moment().endOf("month")],
                "Last Year": [moment().subtract(1, "year").startOf("year"), moment().subtract(1, "year").endOf("year")],
            }
        }, function(start, end, label) {
            startDate = start.format("YYYY-MM-DD");
            endDate = end.format("YYYY-MM-DD");
            table.DataTable().ajax.reload();
        });

        const documentTitle = 'Sales Summary Report';
        var buttons = new $.fn.dataTable.Buttons(table, {
            buttons: [
                {
                    extend: 'copyHtml5',
                    title: documentTitle
                },
                {
                    extend: 'excelHtml5',
                    title: documentTitle
                },
                {
                    extend: 'csvHtml5',
                    title: documentTitle
                },
                {
                    extend: 'pdfHtml5',
                    title: documentTitle
                }
            ]
        }).container().appendTo($('#datatable_buttons'));

        // Hook dropdown menu click event to datatable export buttons
        const exportButtons = document.querySelectorAll('#datatables_menu [data-kt-export]');
        exportButtons.forEach(exportButton => {
            exportButton.addEventListener('click', e => {
                e.preventDefault();

                // Get clicked export value
                const exportValue = e.target.getAttribute('data-kt-export');
                const target = document.querySelector('.dt-buttons .buttons-' + exportValue);

                // Trigger click event on hidden datatable export buttons
                target.click();
            });
        });

        $('#tableSearch').keyup(function(){
            table.DataTable().search($(this).val()).draw();
        });

        store_select.on('select2:select', function(){
            table.DataTable().ajax.reload()
        })

        function numberWithCommas(x) {
            return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // Price History Date Variables (separate from sales insight)
        let priceHistoryStartDate = moment().subtract(1, 'year').startOf('day');
        let priceHistoryEndDate = moment().endOf('day');

        // Price History Chart
        var priceHistoryElement = document.getElementById('price_history_chart');
        var priceHistoryHeight = parseInt(KTUtil.css(priceHistoryElement, 'height'));
        var priceHistoryOptions = {
            chart: {
                fontFamily: 'inherit',
                type: 'line',
                height: priceHistoryHeight,
                toolbar: {
                    show: false
                }
            },
            stroke: {
                curve: 'smooth',
                show: true,
                width: 3
            },
            legend: {
                show: false
            },
            dataLabels: {
                enabled: false
            },
            xaxis: {
                categories: [],
                axisBorder: {
                    show: false,
                },
                axisTicks: {
                    show: false
                },
                labels: {
                    show: false,
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: labelColor,
                        fontSize: '12px'
                    },
                    formatter: function(value){
                        return convertNumberShorter(value);
                    }
                }
            },
            fill: {
                opacity: 1
            },
            states: {
                normal: {
                    filter: {
                        type: 'none',
                        value: 0
                    }
                },
                hover: {
                    filter: {
                        type: 'none',
                        value: 0
                    }
                },
                active: {
                    allowMultipleDataPointsSelection: false,
                    filter: {
                        type: 'none',
                        value: 0
                    }
                }
            },
            tooltip: {
                style: {
                    fontSize: '12px'
                },
                y: {
                    formatter: function (val) {
                        return numberWithCommas(val.toFixed(2));
                    }
                }
            },
            colors: [baseColor, KTUtil.getCssVariableValue('--bs-warning')],
            grid: {
                borderColor: borderColor,
                strokeDashArray: 4,
                yaxis: {
                    lines: {
                        show: true
                    }
                },
                padding: {
                    top: 0,
                    right: 0,
                    bottom: 0,
                    left: 0
                }
            },
            series: [],
            noData: {
                text: 'No price changes recorded'
            }
        };

        var priceHistoryChart = new ApexCharts(priceHistoryElement, priceHistoryOptions);
        priceHistoryChart.render();

        // Fetch price history data
        function loadPriceHistory() {
            $.ajax({
                url: "{{ route('item.price-history', $item->id) }}",
                type: 'GET',
                data: {
                    startDate: priceHistoryStartDate.format('YYYY-MM-DD'),
                    endDate: priceHistoryEndDate.format('YYYY-MM-DD')
                },
                success: function(response) {
                    // Update chart
                    priceHistoryChart.updateOptions({
                        series: [
                            {
                                name: 'Price',
                                data: response.chart.prices
                            },
                            {
                                name: 'Cost',
                                data: response.chart.costs
                            }
                        ],
                        xaxis: {
                            categories: response.chart.dates
                        }
                    });

                    // Update table
                    var tbody = $('#price_history_table tbody');
                    tbody.empty();

                    if (response.data.length === 0) {
                        tbody.append('<tr><td colspan="4" class="text-center text-muted py-5 fs-7">No price changes recorded</td></tr>');
                    } else {
                        response.data.forEach(function(row) {
                            var oldPrice = row.old_price !== null ? '₱ ' + numberWithCommas(parseFloat(row.old_price).toFixed(2)) : '-';
                            var newPrice = row.new_price !== null ? '₱ ' + numberWithCommas(parseFloat(row.new_price).toFixed(2)) : '-';
                            tbody.append(
                                '<tr>' +
                                '<td class="text-gray-600 fs-7">' + row.date + '</td>' +
                                '<td class="text-gray-600 fs-7">' + oldPrice + '</td>' +
                                '<td class="text-gray-800 fw-bold fs-7">' + newPrice + '</td>' +
                                '<td class="text-gray-500 fs-7">' + row.changed_by + '</td>' +
                                '</tr>'
                            );
                        });
                    }
                }
            });
        }

        // Initialize Price History Date Picker
        $('#priceHistoryDatePicker').daterangepicker({
            startDate: priceHistoryStartDate,
            endDate: priceHistoryEndDate,
            showDropdowns: true,
            ranges: {
                "Today": [moment(), moment()],
                "Last 7 Days": [moment().subtract(6, "days"), moment()],
                "Last 30 Days": [moment().subtract(29, "days"), moment()],
                "This Month": [moment().startOf("month"), moment().endOf("month")],
                "Last 3 Months": [moment().subtract(3, "month").startOf("month"), moment().endOf("month")],
                "Last 6 Months": [moment().subtract(6, "month").startOf("month"), moment().endOf("month")],
                "This Year": [moment().startOf("year"), moment().endOf("year")],
                "Last Year": [moment().subtract(1, "year").startOf("year"), moment().subtract(1, "year").endOf("year")],
                "All Time": [moment().subtract(10, "year"), moment()],
            }
        }, function(start, end, label) {
            priceHistoryStartDate = start;
            priceHistoryEndDate = end;
            loadPriceHistory();
        });

        // Load price history on page load
        loadPriceHistory();

        // Wholesale Price Tiers
        const tiersUrl = "{{ route('items.wholesale-tiers.index', $item->id) }}";
        const tiersStoreUrl = "{{ route('items.wholesale-tiers.store', $item->id) }}";
        const tierModal = new bootstrap.Modal(document.getElementById('tierModal'));

        var itemPrice = {{ $item->price }};
        var itemCost = {{ $item->cost }};

        function tierMarginCell(effectivePrice) {
            if (!itemCost || itemCost <= 0) {
                return '<td class="text-muted fs-7">—</td>';
            }
            var margin = ((effectivePrice - itemCost) / itemCost) * 100;
            var cls = margin >= 0 ? 'text-success' : 'text-danger';
            var sign = margin >= 0 ? '+' : '';
            return '<td class="fw-bold fs-7 ' + cls + '">' + sign + margin.toFixed(1) + '%</td>';
        }

        function loadTiers() {
            $.get(tiersUrl, function(response) {
                var data = response.data ? (response.data.tiers || response.data) : response;
                if (!Array.isArray(data)) data = [];
                var tbody = $('#wholesale_tiers_table tbody');
                tbody.empty();
                if (data.length === 0) {
                    $('#wholesale_tiers_table').addClass('d-none');
                    $('#noTiersMessage').removeClass('d-none');
                } else {
                    $('#wholesale_tiers_table').removeClass('d-none');
                    $('#noTiersMessage').addClass('d-none');
                    data.forEach(function(tier) {
                        var effectivePrice = Math.max(0, itemPrice - parseFloat(tier.discount));
                        tbody.append(
                            '<tr data-tier-id="' + tier.id + '">' +
                            '<td>' + tier.min_qty + '</td>' +
                            '<td>- ₱ ' + numberWithCommas(parseFloat(tier.discount).toFixed(2)) + '</td>' +
                            '<td>₱ ' + numberWithCommas(effectivePrice.toFixed(2)) + '</td>' +
                            tierMarginCell(effectivePrice) +
                            '<td class="text-end">' +
                                '<button class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1 btn-edit-tier" data-id="' + tier.id + '" data-min-qty="' + tier.min_qty + '" data-discount="' + tier.discount + '"><i class="fas fa-edit"></i></button>' +
                                '<button class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm btn-delete-tier" data-id="' + tier.id + '"><i class="fas fa-trash"></i></button>' +
                            '</td>' +
                            '</tr>'
                        );
                    });
                }
            });
        }

        loadTiers();

        $('#btnAddTier').click(function() {
            $('#tierModalTitle').text('Add Price Tier');
            $('#tierEditId').val('');
            $('#tierMinQty').val('');
            $('#tierDiscount').val('');
            $('.text-danger').addClass('d-none').text('');
            tierModal.show();
        });

        $(document).on('click', '.btn-edit-tier', function() {
            var id = $(this).data('id');
            var minQty = $(this).data('min-qty');
            var discount = $(this).data('discount');
            $('#tierModalTitle').text('Edit Price Tier');
            $('#tierEditId').val(id);
            $('#tierMinQty').val(minQty);
            $('#tierDiscount').val(discount);
            $('.text-danger').addClass('d-none').text('');
            tierModal.show();
        });

        $(document).on('click', '.btn-delete-tier', function() {
            var id = $(this).data('id');
            if (confirm('Are you sure you want to delete this tier?')) {
                $.ajax({
                    url: '/admin/items/wholesale-tiers/' + id,
                    type: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    success: function() { loadTiers(); }
                });
            }
        });

        $('#btnSaveTier').click(function() {
            var editId = $('#tierEditId').val();
            var data = {
                min_qty: $('#tierMinQty').val(),
                discount: $('#tierDiscount').val(),
                _token: '{{ csrf_token() }}'
            };

            var url = editId ? '/admin/items/wholesale-tiers/' + editId : tiersStoreUrl;
            var method = editId ? 'PUT' : 'POST';

            $.ajax({
                url: url,
                type: method,
                data: data,
                success: function() {
                    tierModal.hide();
                    loadTiers();
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        var errors = xhr.responseJSON;
                        if (errors.errors) {
                            if (errors.errors.min_qty) {
                                $('#tierMinQtyError').text(errors.errors.min_qty[0]).removeClass('d-none');
                            }
                            if (errors.errors.discount) {
                                $('#tierDiscountError').text(errors.errors.discount[0]).removeClass('d-none');
                            }
                        }
                        if (errors.message) {
                            $('#tierMinQtyError').text(errors.message).removeClass('d-none');
                        }
                    }
                }
            });
        });
    });
</script>
@endsection
