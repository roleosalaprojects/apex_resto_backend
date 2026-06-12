@extends('layout.app')
@section('header')
    - Sales Summary
@endsection
@section('title')
    Sales Summary
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted"><span class="">Reports</span></li>
    <li class="breadcrumb-item text-muted">Sales Summary</li>
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
            title="Time"
    ></x-general.search-table>
@endsection
@section('content')
    {{-- Statistics --}}
    <div class="row g-5 g-xl-8">
        <div class="col-xl-3">
            <!--begin::Statistics Widget 5-->
            <div class="card bg-light-primary hoverable card-xl-stretch mb-xl-8">
                <!--begin::Body-->
                <div class="card-body">
                    <!--begin::Svg Icon | path: icons/duotune/ecommerce/ecm008.svg-->
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M21 10H13V11C13 11.6 12.6 12 12 12C11.4 12 11 11.6 11 11V10H3C2.4 10 2 10.4 2 11V13H22V11C22 10.4 21.6 10 21 10Z" fill="currentColor"/>
                            <path opacity="0.3" d="M12 12C11.4 12 11 11.6 11 11V3C11 2.4 11.4 2 12 2C12.6 2 13 2.4 13 3V11C13 11.6 12.6 12 12 12Z" fill="currentColor"/>
                            <path opacity="0.3" d="M18.1 21H5.9C5.4 21 4.9 20.6 4.8 20.1L3 13H21L19.2 20.1C19.1 20.6 18.6 21 18.1 21ZM13 18V15C13 14.4 12.6 14 12 14C11.4 14 11 14.4 11 15V18C11 18.6 11.4 19 12 19C12.6 19 13 18.6 13 18ZM17 18V15C17 14.4 16.6 14 16 14C15.4 14 15 14.4 15 15V18C15 18.6 15.4 19 16 19C16.6 19 17 18.6 17 18ZM9 18V15C9 14.4 8.6 14 8 14C7.4 14 7 14.4 7 15V18C7 18.6 7.4 19 8 19C8.6 19 9 18.6 9 18Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <!--end::Svg Icon-->
                    <div class="text-gray-900 fw-bold fs-2 mt-8" id="sales">₱ 0</div>
                    <div class="fw-semibold text-gray-600 fs-3">Sales</div>
                </div>
                <!--end::Body-->
            </div>
            <!--end::Statistics Widget 5-->
        </div>
        <div class="col-xl-3">
            <!--begin::Statistics Widget 5-->
            <div class="card bg-light-danger hoverable card-xl-stretch mb-xl-8">
                <!--begin::Body-->
                <div class="card-body">
                    <!--begin::Svg Icon | path: icons/duotune/ecommerce/ecm002.svg-->
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14.4 11H2.99999C2.39999 11 1.99999 11.4 1.99999 12C1.99999 12.6 2.39999 13 2.99999 13H14.4V11Z" fill="currentColor"/>
                            <path d="M17.7762 13.2561C18.4572 12.5572 18.4572 11.4429 17.7762 10.7439L13.623 6.48107C13.1221 5.96697 12.25 6.32158 12.25 7.03934V16.9607C12.25 17.6785 13.1221 18.0331 13.623 17.519L17.7762 13.2561Z" fill="currentColor"/>
                            <rect opacity="0.5" width="2" height="16" rx="1" transform="matrix(-1 0 0 1 22 4)" fill="currentColor"/>
                        </svg>
                    </span>
                    <!--end::Svg Icon-->
                    <div class="text-gray-900 fw-bold fs-2 mt-8" id="refunds">₱ 0</div>
                    <div class="fw-semibold text-gray-900 fs-3">Refunds</div>
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
                    <!--begin::Svg Icon | path: icons/duotune/graphs/gra005.svg-->
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path opacity="0.3" d="M17.7 5.59999C16.7 5.19999 15.5 5.50003 14.8 6.20003L10.2 10.8C9.5 11.5 8.4 11.8 7.5 11.5L5.10001 10.8V18.9H20.1V6.40004L17.7 5.59999Z" fill="currentColor"/>
                            <path d="M21 18H6V3C6 2.4 5.6 2 5 2C4.4 2 4 2.4 4 3V18H3C2.4 18 2 18.4 2 19C2 19.6 2.4 20 3 20H4V21C4 21.6 4.4 22 5 22C5.6 22 6 21.6 6 21V20H21C21.6 20 22 19.6 22 19C22 18.4 21.6 18 21 18Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <!--end::Svg Icon-->
                    <div class="text-gray-900 fw-bold fs-2 mt-8" id="revenue">₱ 0</div>
                    <div class="fw-semibold text-gray-900 fs-3">Revenue</div>
                </div>
                <!--end::Body-->
            </div>
            <!--end::Statistics Widget 5-->
        </div>
        <div class="col-xl-3">
            <!--begin::Statistics Widget 5-->
            <div class="card bg-light-info hoverable card-xl-stretch mb-5 mb-xl-8">
                <!--begin::Body-->
                <div class="card-body">
                    <!--begin::Svg Icon | path: icons/duotune/graphs/gra005.svg-->
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path opacity="0.3" d="M3 3V17H7V21H15V9H20V3H3Z" fill="currentColor"/>
                            <path d="M20 22H3C2.4 22 2 21.6 2 21V3C2 2.4 2.4 2 3 2H20C20.6 2 21 2.4 21 3V21C21 21.6 20.6 22 20 22ZM19 4H4V8H19V4ZM6 18H4V20H6V18ZM6 14H4V16H6V14ZM6 10H4V12H6V10ZM10 18H8V20H10V18ZM10 14H8V16H10V14ZM10 10H8V12H10V10ZM14 18H12V20H14V18ZM14 14H12V16H14V14ZM14 10H12V12H14V10ZM19 14H17V20H19V14ZM19 10H17V12H19V10Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <!--end::Svg Icon-->
                    <div class="text-gray-900 fw-bold fs-2 mt-8" id="net_sales">₱ 0</div>
                    <div class="fw-semibold text-gray-900 fs-3">Net Sales</div>
                </div>
                <!--end::Body-->
            </div>
            <!--end::Statistics Widget 5-->
        </div>
    </div>
    <div class="row g-5 g-xl-8 mb-7">
        <div class="col-xl-3">
            <!--begin::Statistics Widget 5-->
            <div class="card bg-light-primary hoverable card-xl-stretch mb-xl-8">
                <!--begin::Body-->
                <div class="card-body">
                    <!--begin::Svg Icon | path: icons/duotune/ecommerce/ecm008.svg-->
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17.4 7H4C3.4 7 3 7.4 3 8C3 8.6 3.4 9 4 9H17.4V7ZM6.60001 15H20C20.6 15 21 15.4 21 16C21 16.6 20.6 17 20 17H6.60001V15Z" fill="currentColor"/>
                            <path opacity="0.3" d="M17.4 3V13L21.7 8.70001C22.1 8.30001 22.1 7.69999 21.7 7.29999L17.4 3ZM6.6 11V21L2.3 16.7C1.9 16.3 1.9 15.7 2.3 15.3L6.6 11Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <!--end::Svg Icon-->
                    <div class="text-gray-900 fw-bold fs-2 mt-8" id="avgSales">₱ 0</div>
                    <div class="fw-semibold text-gray-900 fs-3">Avg. Daily Sales</div>
                </div>
                <!--end::Body-->
            </div>
            <!--end::Statistics Widget 5-->
        </div>
        <div class="col-xl-3">
            <!--begin::Statistics Widget 5-->
            <div class="card bg-light-danger hoverable card-xl-stretch mb-xl-8">
                <!--begin::Body-->
                <div class="card-body">
                    <!--begin::Svg Icon | path: icons/duotune/ecommerce/ecm008.svg-->
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19.2 13.8L13.7 8.3C13.3 7.9 12.7 7.9 12.3 8.3L9 11.6L3.7 6.3C3.3 5.9 2.7 5.9 2.3 6.3C1.9 6.7 1.9 7.3 2.3 7.7L8.3 13.7C8.7 14.1 9.3 14.1 9.7 13.7L13 10.4L17.8 15.2L19.2 13.8Z" fill="currentColor"/>
                            <path opacity="0.3" d="M22 10.9V17C22 17.6 21.6 18 21 18H14.9L22 10.9Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <!--end::Svg Icon-->
                    <div class="text-gray-900 fw-bold fs-2 mt-8" id="minSales">₱ 0</div>
                    <div class="fw-semibold text-gray-900 fs-3">Min. Sales</div>
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
                    <!--begin::Svg Icon | path: icons/duotune/ecommerce/ecm008.svg-->
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17.8 8.79999L13 13.6L9.7 10.3C9.3 9.89999 8.7 9.89999 8.3 10.3L2.3 16.3C1.9 16.7 1.9 17.3 2.3 17.7C2.5 17.9 2.7 18 3 18C3.3 18 3.5 17.9 3.7 17.7L9 12.4L12.3 15.7C12.7 16.1 13.3 16.1 13.7 15.7L19.2 10.2L17.8 8.79999Z" fill="currentColor"/>
                            <path opacity="0.3" d="M22 13.1V7C22 6.4 21.6 6 21 6H14.9L22 13.1Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <!--end::Svg Icon-->
                    <div class="text-gray-900 fw-bold fs-2 mt-8" id="maxSales">₱ 0</div>
                    <div class="fw-semibold text-gray-900 fs-3">Max. Sales</div>
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
                    <!--begin::Svg Icon | path: icons/duotune/ecommerce/ecm008.svg-->
                    <span class="svg-icon svg-icon-dark svg-icon-3x ms-n1">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13 5.91517C15.8 6.41517 18 8.81519 18 11.8152C18 12.5152 17.9 13.2152 17.6 13.9152L20.1 15.3152C20.6 15.6152 21.4 15.4152 21.6 14.8152C21.9 13.9152 22.1 12.9152 22.1 11.8152C22.1 7.01519 18.8 3.11521 14.3 2.01521C13.7 1.91521 13.1 2.31521 13.1 3.01521V5.91517H13Z" fill="currentColor"/>
                            <path opacity="0.3" d="M19.1 17.0152C19.7 17.3152 19.8 18.1152 19.3 18.5152C17.5 20.5152 14.9 21.7152 12 21.7152C9.1 21.7152 6.50001 20.5152 4.70001 18.5152C4.30001 18.0152 4.39999 17.3152 4.89999 17.0152L7.39999 15.6152C8.49999 16.9152 10.2 17.8152 12 17.8152C13.8 17.8152 15.5 17.0152 16.6 15.6152L19.1 17.0152ZM6.39999 13.9151C6.19999 13.2151 6 12.5152 6 11.8152C6 8.81517 8.2 6.41515 11 5.91515V3.01519C11 2.41519 10.4 1.91519 9.79999 2.01519C5.29999 3.01519 2 7.01517 2 11.8152C2 12.8152 2.2 13.8152 2.5 14.8152C2.7 15.4152 3.4 15.7152 4 15.3152L6.39999 13.9151Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <!--end::Svg Icon-->
                    <div class="text-gray-900 fw-bold fs-2 mt-8" id="profitMargin"> 0</div>
                    <div class="fw-semibold text-gray-900 fs-3">Profit Margin</div>
                </div>
                <!--end::Body-->
            </div>
            <!--end::Statistics Widget 5-->
        </div>
    </div>
    {{-- Chart --}}
    <div class="card card-bordered mb-7">
        <div class="card-body">
            <div id="sales_chart" style="height: 500px;"></div>
        </div>
    </div>
    <div class="card card-flush">
        <div class="card-body">
            <x-data-table.table
                table-id="table"
            >
                <th>Date</th>
                <th>Sales</th>
                <th>Refunds</th>
                <th>Net Sales</th>
                <th>Revenue</th>
            </x-data-table.table>
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
        $(document).ready(function(){
            $startDate = moment().startOf('day');
            $endDate = moment().endOf('day');
            // Select2
            var store_select = $("#store_select");

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
            })

            // Chart
            var element = document.getElementById('sales_chart');

            console.log(KTUtil.getCssVariableValue('--bs-danger'));

            var height = parseInt(KTUtil.css(element, 'height'));
            var labelColor = KTUtil.getCssVariableValue('--bs-gray-500');
            var borderColor = KTUtil.getCssVariableValue('--bs-gray-200');

            var baseColor = KTUtil.getCssVariableValue('--bs-danger');
            var secondaryColor = KTUtil.getCssVariableValue('--bs-primary');
            var revenueColor = KTUtil.getCssVariableValue('--bs-success');
            var backColor = KTUtil.getCssVariableValue('--bs-primary-bg-subtle');

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
                colors: [baseColor, revenueColor, secondaryColor, backColor],
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

            // Table Options
            var tableOptions = {
                filter: true,
                responsive: true,
                serverside: true,
                processing: true,
                columns: [
                    {'data': 'time'},
                    {'data': 'sales'},
                    {'data': "refunds"},
                    {'data': 'sales'},
                    {'data': 'revenue'},
                ],
                columnDefs: [
                    {
                        targets: 1,
                        render: function(data, type, full){
                            return numberWithCommas(full.sales.toFixed(2));
                        }
                    },
                    {
                        targets: 2,
                        render: function(data, type, full){
                            return numberWithCommas(full.refunds.toFixed(2));
                        }
                    },
                    {
                        targets: 3,
                        render: function(data, type, full){
                            return numberWithCommas((parseFloat(full.sales) - parseFloat(full.refunds)).toFixed(2));
                        }
                    },
                    {
                        targets: 4,
                        render: function(data, type, full){
                            return numberWithCommas(full.revenue.toFixed(2));
                        }
                    }
                ],
                ajax: {
                    type: 'get',
                    data: {
                        'startDate': function() { return $startDate},
                        'endDate': function(){return $endDate},
                        'store_select': function(){return store_select.val()},
                    },
                    url: "{{ route('reports.sales_summary.data') }}",
                    dataSrc: function(response){
                        let sales = response.sales.sales != null ? response.sales.sales - response.sales.refunds : 0;
                        (response.sales.sales != null) ? $("#sales").text(`₱ ` + numberWithCommas((response.sales.sales).toFixed(2))) : $("#sales").text(`₱ 0.00`);
                        (response.sales.refunds != null) ? $("#refunds").text(`₱ ` + numberWithCommas((response.sales.refunds).toFixed(2))) : $("#refunds").text(`₱ 0.00`);
                        (response.sales.revenue != null) ? $("#revenue").text(`₱ ` + numberWithCommas((response.sales.revenue).toFixed(2))) : $("#revenue").text(`₱ 0.00`);
                        (response.sales.sales != null) ? $("#net_sales").text(`₱ ` + numberWithCommas((response.sales.sales - response.sales.refunds).toFixed(2))) : $("#net_sales").text(`₱ 0.00`);
                        chart.updateOptions({
                            series:[
                                {
                                    name: 'Refunds',
                                    type: 'bar',
                                    stacked: true,
                                    data: response.chart.map(function(val){
                                        return val.refunds.toFixed(2)
                                    })
                                },
                                {
                                    name: 'Revenue',
                                    type: 'bar',
                                    // stacked: true,
                                    data: response.chart.map(function(val){
                                        return val.revenue.toFixed(2)
                                    })
                                },
                                {
                                    name: 'Sales',
                                    type: 'bar',
                                    stacked: true,
                                    data: response.chart.map(function(val){
                                        return val.sales.toFixed(2)
                                    })
                                },
                                {
                                    name: 'Net Sales',
                                    type: 'area',
                                    data: response.chart.map(function(val){
                                        return (val.sales - val.refunds).toFixed(2)
                                    })
                                }
                            ],
                            xaxis: {
                                categories: response.chart.map(function(e){
                                    return e.time
                                })
                            }
                        });
                        // update average, min, max sales stats
                        // console.log(response.table.original.data.length);
                        if(response.table.original.data.length > 0){
                            let avg = 0;
                            let min = response.table.original.data[0].sales;
                            let max = 0;
                            let profitMargin = 0;
                            response.table.original.data.map((e) => {
                                let netSales = e.sales - e.refunds
                                avg += netSales;
                                min = netSales;
                                min = min < netSales ? min : netSales
                                max = netSales > max ? netSales : max
                                profitMargin += e.revenue;
                            });
                            avg = avg / response.table.original.data.length;
                            profitMargin = (profitMargin/sales) * 100;
                            $('#avgSales').text(`₱ ` + numberWithCommas(avg.toFixed(2)));
                            $('#minSales').text(`₱ ` + numberWithCommas(min.toFixed(2)));
                            $('#maxSales').text(`₱ ` + numberWithCommas(max.toFixed(2)));
                            $('#profitMargin').text(numberWithCommas(profitMargin.toFixed(2)) + ' %');
                        }
                        return response.table.original.data;
                    }

                }
            }

            var table = $("#table");
            let dataTable = table.DataTable(tableOptions);
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

            $('#daterangepicker').daterangepicker({
                startDate: $startDate,
                endDate: $endDate,
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
                $startDate = start.format("YYYY-MM-DD");
                $endDate = end.format("YYYY-MM-DD");
                table.DataTable().ajax.reload();
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
        });
    </script>
@endsection
