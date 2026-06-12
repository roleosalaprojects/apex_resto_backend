@extends('layout.app')
@section('header')
    - Sales By Item
@endsection
@section('title')
    Sales By Item
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a href="{{ route('admin.home') }}" class="">Dashboard</a></li>
    <li class="breadcrumb-item "><span class="">Reports</span></li>
    <li class="breadcrumb-item ">Sales By Item</li>
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
            title="Product"
    ></x-general.search-table>
@endsection
@section('content')
    {{-- Chart --}}
    <div class="card card-bordered mb-7">
        <div class="card-body">
            <div id="categories_chart" style="height: 500px;"></div>
        </div>
    </div>
    <div class="card card-flush">
        <div class="card-body">
            <div class="table-responsive">
                <x-data-table.table
                    table-id="table">
                    <th>Item / Product</th>
                    <th>Items Sold</th>
                    <th>Sales</th>
                    <th>Revenue</th>
                </x-data-table.table>
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
        $(document).ready(function(){
            $startDate = moment().startOf('day');
            $endDate = moment().endOf('day');

            // Select2
            var store_select = $("#store_select");

            // Chart
            var element = document.getElementById('categories_chart');

            var height = parseInt(KTUtil.css(element, 'height'));
            var labelColor = KTUtil.getCssVariableValue('--bs-gray-500');
            var borderColor = KTUtil.getCssVariableValue('--bs-gray-200');
            var baseColor = KTUtil.getCssVariableValue('--bs-primary');
            var secondaryColor = KTUtil.getCssVariableValue('--bs-info');
            var tertiaryColor = KTUtil.getCssVariableValue('--bs-info');

            if (!element) {
                return;
            }

            var options = {
                chart: {
                    fontFamily: 'inherit',
                    height: height,
                    toolbar: {
                        show: false
                    }
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        borderRadius: 3,
                        borderRadiusApplication: 'around',
                        borderRadiusWhenStacked: 'first',
                        columnWidth: ['40%'],
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
                            return numberWithCommas(value);
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
                colors: [baseColor, secondaryColor, tertiaryColor],
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
            };

            var chart = new ApexCharts(element, options);
            chart.render();

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

            var tableOptions = {
                filter: true,
                responsive: true,
                serverside: true,
                processing: true,
                columns: [
                    {'data': 'item'},
                    {'data': 'items_sold'},
                    {'data': 'net_sales'},
                    {'data': 'revenue'},
                ],
                columnDefs: [
                    {
                        targets: 0,
                        render: function (data, type, full) {
                            return `<a class="text-gray-800 text-hover-primary fs-6" href='/admin/items/${full.item_id}' target="_blank">${full.item}</a>`
                        }
                    },
                    {
                        targets: 1,
                        render: function(data, type, full){
                            console.log(full)
                            return numberWithCommas(full.items_sold.toFixed(2));
                        }
                    },
                    {
                        targets: 2,
                        render: function(data, type, full){
                            return numberWithCommas(full.net_sales.toFixed(2));
                        }
                    },
                    {
                        targets: 3,
                        render: function(data, type, full){
                            return numberWithCommas(full.revenue.toFixed(2));
                        }
                    },
                ],
                ajax: {
                    type: 'get',
                    data: {
                        'startDate': function() { return $startDate},
                        'endDate': function(){return $endDate},
                        'store_select': function(){return store_select.val()},
                    },
                    url: '{{ route('reports.sales.items.data') }}',
                    dataSrc: function(response){
                        chart.updateOptions({
                            series:[
                                {
                                    name: 'Items Sold',
                                    type: 'bar',
                                    data: response.top.map(function(val){
                                        return val.items_sold.toFixed(2)
                                    })
                                },
                                {
                                    name: 'Net Sales',
                                    type: 'bar',
                                    data: response.top.map(function(val){
                                        return val.net_sales.toFixed(2)
                                    })
                                },
                            ],
                            xaxis: {
                                categories: response.top.map(function(e){
                                    return e.item
                                })
                            }
                        });
                        return response.table.original.data;
                    }
                }
            }

            var table = $("#table");
            let dataTable = table.DataTable(tableOptions);
            const documentTitle = 'Sales By Item Report';
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
        })
    </script>
@endsection
