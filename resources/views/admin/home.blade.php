@extends('layout.app')

@section('header')
    - Dashboard
@endsection
@section('title')
    Dashboard
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item text-muted">Dashboard</li>
@endsection
@section('actions')
    <x-data-table.actions
            showExport="false"
    >
        <div class="px-7 py-5">
            <div class="mb-5">
                <label for="daterangepicker" class="form-label">Date</label>
                <input class="form-control form-control-solid form-control" placeholder="Pick date range"
                       id="daterangepicker"/>
            </div>
        </div>
    </x-data-table.actions>
@endsection
@section('content')
    {{-- KPI Cards Row --}}
    <div class="row g-5 g-xl-10">
        <div class="col-12 col-sm-6 col-md-4 col-xl">
            <x-widgets.home.cards.flush-widget
                    sum-id="salesSum"
                    title="Total Sales"
                    chart-id="salesChart"
                    use-white="true"
                    style="background: linear-gradient(180deg, #1858FD 0%, #1652EA 100%); box-shadow: 0px 14px 40px 0px rgba(24, 85, 243, 0.20);"
            ></x-widgets.home.cards.flush-widget>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-xl">
            <x-widgets.home.cards.flush-widget
                    sum-id="netSalesSum"
                    title="Net Sales"
                    chart-id="netSalesChart"
            ></x-widgets.home.cards.flush-widget>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-xl">
            <x-widgets.home.cards.flush-widget
                    sum-id="refundsSum"
                    title="Refunds"
                    chart-id="refundsChart"
            ></x-widgets.home.cards.flush-widget>
        </div>
        <div class="col-12 col-sm-6 col-md-6 col-xl">
            <x-widgets.home.cards.flush-widget
                    sum-id="receiptsSum"
                    title="Receipts"
                    chart-id="receiptsChart"
                    use-currency="false"
            ></x-widgets.home.cards.flush-widget>
        </div>
        <div class="col-12 col-sm-6 col-md-6 col-xl">
            <x-widgets.home.cards.flush-widget
                    sum-id="expensesSum"
                    title="Total Expenses"
                    chart-id="expensesChart"
            ></x-widgets.home.cards.flush-widget>
        </div>
    </div>

    {{-- Cumulative Sales Chart --}}
    <div class="row g-5 g-xl-10 mt-2 mt-xl-5">
        <div class="col-12">
            <div class="card card-flush card-px-0 h-lg-500px">
                <div class="card-header pt-1">
                    <h3 class="card-title align-items-start flex-column px-6">
                        <span class="card-label fw-bold text-dark">Sales Chart</span>
                        <span class="text-gray-400 pt-2 fw-semibold fs-6">Cumulative Sales Chart</span>
                    </h3>
                </div>
                <div class="card-body d-flex flex-center">
                    <div id="cumulativeChart" class="w-100 h-400px"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Dashboard Widgets Row --}}
    <div class="row g-5 g-xl-10 mt-2 mt-xl-5">
        <div class="col-12 col-lg-6 col-xl-4">
            <livewire:admin.dashboard.revenue-comparison />
        </div>
        <div class="col-12 col-lg-6 col-xl-4">
            <livewire:admin.dashboard.top-products />
        </div>
        <div class="col-12 col-xl-4">
            <livewire:admin.dashboard.staff-leaderboard />
        </div>
    </div>

    {{-- Sales Ticker Row --}}
    <div class="row g-5 g-xl-10 mt-2 mt-xl-5">
        <div class="col-12">
            <livewire:admin.dashboard.sales-ticker />
        </div>
    </div>
@endsection
@section('scripts')
    <script src="{{ asset('assets/js/pages/home/widgets.js') }}"></script>
    <script>
        $(document).ready(function () {
            $startDate = moment().startOf('day');
            $endDate = moment().endOf('day');
            // Select2
            var store_select = $("#store_select");
            // SUM
            var salesSum = $("#salesSum");
            var refundsSum = $("#refundsSum");
            var netSalesSum = $("#netSalesSum");
            var receiptsSum = $("#receiptsSum");
            var expensesSum = $("#expensesSum");

            // Chart
            var element = document.getElementById('cumulativeChart');
            var salesChartElement = document.getElementById('salesChart');
            var refundsChartElement = document.getElementById('refundsChart');
            var netSalesChartElement = document.getElementById('netSalesChart');
            var receiptsChartElement = document.getElementById('receiptsChart');
            var expensesChartElement = document.getElementById('expensesChart');

            var height = parseInt(KTUtil.css(element, 'height'));
            var salesChartHeight = parseInt(KTUtil.css(salesChartElement, 'height'));

            var borderColor = KTUtil.getCssVariableValue('--bs-gray-200');

            var baseColor = KTUtil.getCssVariableValue('--bs-danger');
            var secondaryColor = KTUtil.getCssVariableValue('--bs-primary');
            var salesColor = KTUtil.getCssVariableValue('--bs-white');
            var revenueColor = KTUtil.getCssVariableValue('--bs-success');
            var receiptsColor = KTUtil.getCssVariableValue('--bs-dark');
            var backColor = KTUtil.getCssVariableValue('--bs-info-light');

            var netSalesColor = KTUtil.getCssVariableValue('--bs-info');

            // Chart Options
            var options = {
                grid: {
                    padding: {
                        left: 0,
                        right: 0
                    }
                },
                chart: {
                    fontFamily: 'inherit',
                    stacked: true,
                    height: height,
                    toolbar: {
                        show: false
                    },
                    sparkline: true,
                },
                plotOptions: {},
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
                    labels: {
                        show: false
                    }
                },
                yaxis: {
                    labels: {
                        show: false
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
            var salesCharOptions = homeCardChartWidget(salesChartHeight, salesColor);
            var refundsChartOptions = homeCardChartWidget(salesChartHeight, secondaryColor);
            var netSalesChartOptions = homeCardChartWidget(salesChartHeight, netSalesColor);
            var receiptsChartOptions = homeCardChartWidget(salesChartHeight, receiptsColor);
            var expensesChartOptions = homeCardChartWidget(salesChartHeight, baseColor);

            var chart = new ApexCharts(element, options);
            var salesChart = new ApexCharts(salesChartElement, salesCharOptions);
            var refundsChart = new ApexCharts(refundsChartElement, refundsChartOptions);
            var netSalesChart = new ApexCharts(netSalesChartElement, netSalesChartOptions);
            var receiptsChart = new ApexCharts(receiptsChartElement, receiptsChartOptions);
            var expensesChart = new ApexCharts(expensesChartElement, expensesChartOptions);

            chart.render();
            salesChart.render();
            refundsChart.render();
            netSalesChart.render();
            receiptsChart.render();
            expensesChart.render();

            // Init Select2
            store_select.select2({
                dropdownParent: $("#card_actions"),
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
            }, function (start, end, label) {
                $startDate = start.format("YYYY-MM-DD");
                $endDate = end.format("YYYY-MM-DD");
                getData();
            });

            // Initialize data
            getData();

            store_select.on('select2:select', function () {
                getData();
            })

            function numberWithCommas(x) {
                return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }

            function getData() {
                $.ajax({
                    type: 'get',
                    data: {
                        startDate: () => $startDate,
                        endDate: () => $endDate,
                        store_select: () => store_select.val(),
                    },
                    url: '{{ route("dashboard.default") }}',
                    success: function (response) {
                        (response.data.sales != null) ? salesSum.text(numberWithCommas(response.data.sales.toFixed(2))) : '';
                        (response.data.refunds != null) ? refundsSum.text(numberWithCommas(response.data.refunds.toFixed(2))) : '';
                        (response.data.net_sales != null) ? netSalesSum.text(numberWithCommas(response.data.net_sales.toFixed(2))) : '';
                        (response.data.receipts > 0) ? receiptsSum.text(numberWithCommas(response.data.receipts)) : '';
                        expensesSum.text(numberWithCommas((response.data.expenses != null ? response.data.expenses : 0).toFixed(2)));
                        expensesChart.updateOptions({
                            series: [
                                {
                                    name: 'Expenses',
                                    type: 'line',
                                    data: response.chart.map(function (val) {
                                        return (val.expenses != null ? val.expenses : 0).toFixed(2);
                                    })
                                }
                            ],
                            xaxis: {
                                categories: response.chart.map(function (e) {
                                    return e.time
                                })
                            }
                        });
                        chart.updateOptions({
                            series: [
                                {
                                    name: 'Refunds',
                                    type: 'bar',
                                    stacked: true,
                                    data: response.chart.map(function (val) {
                                        return val.refunds.toFixed(2)
                                    })
                                },
                                {
                                    name: 'Revenue',
                                    type: 'bar',
                                    stacked: true,
                                    data: response.chart.map(function (val) {
                                        return val.revenue.toFixed(2)
                                    })
                                },
                                {
                                    name: 'Sales',
                                    type: 'bar',
                                    data: response.chart.map(function (val) {
                                        return val.sales.toFixed(2)
                                    })
                                },
                                {
                                    name: 'Net Sales',
                                    type: 'area',
                                    data: response.chart.map(function (val) {
                                        return (val.sales - val.refunds).toFixed(2)
                                    })
                                }
                            ],
                            xaxis: {
                                categories: response.chart.map(function (e) {
                                    return e.time
                                })
                            }
                        });
                        salesChart.updateOptions({
                            series: [
                                {
                                    name: 'Sales',
                                    type: 'line',
                                    stacked: true,
                                    data: response.chart.map(function (val) {
                                        return val.sales.toFixed(2)
                                    })
                                },
                            ],
                            xaxis: {
                                categories: response.chart.map(function (e) {
                                    return e.time
                                })
                            }
                        });
                        refundsChart.updateOptions({
                            series: [
                                {
                                    name: 'Refunds',
                                    type: 'line',
                                    stacked: true,
                                    data: response.chart.map(function (val) {
                                        return val.refunds.toFixed(2)
                                    })
                                },
                            ],
                            xaxis: {
                                categories: response.chart.map(function (e) {
                                    return e.time
                                })
                            }
                        });
                        netSalesChart.updateOptions({
                            series: [
                                {
                                    name: 'Net Sales',
                                    type: 'line',
                                    stacked: true,
                                    data: response.chart.map(function (val) {
                                        return (val.sales - val.refunds).toFixed(2)
                                    })
                                },
                            ],
                            xaxis: {
                                categories: response.chart.map(function (e) {
                                    return e.time
                                })
                            }
                        });
                        receiptsChart.updateOptions({
                            series: [
                                {
                                    name: 'Receipts',
                                    type: 'line',
                                    stacked: true,
                                    data: response.chart.map(function (val) {
                                        return val.receipts
                                    })
                                },
                            ],
                            xaxis: {
                                categories: response.chart.map(function (e) {
                                    return e.time
                                })
                            }
                        });
                    }
                })
            }
        });
    </script>
@endsection
  