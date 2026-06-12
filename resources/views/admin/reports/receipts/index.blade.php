@extends('admin.layouts.master')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Receipts</li>
@endsection
@section('title')
    Receipts
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="text-justify">

                        <div class="form-group">
                            <label><h3>Select Date</h3></label>

                            <div class="input-group">
                                <div class="input-group-prepend">
                        <span class="input-group-text">
                          <i class="far fa-calendar-alt"></i>
                        </span>
                                </div>
                                <input type="text" class="form-control float-right" id="reservation" name="daterange">
                            </div>
                            <!-- /.input group -->
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-lg-3 col-xl-3">
                            <h3><strong>GROSS SALES:</strong></h3>
                            <h2>₱ <span id="gross">0</span></h2>
                        </div>
                        <div class="col-md-3 col-lg-3 col-xl-3">
                            <h3><strong>REFUNDS:</strong></h3>
                            <h2>₱ <span id="refunds">0</span></h2>
                        </div>
                        <div class="col-md-3 col-lg-3 col-xl-3">
                            <h3><strong>NET SALES:</strong></h3>
                            <h2>₱ <span id="sales">0</span></h2>
                        </div>
                        <div class="col-md-3 col-lg-3 col-xl-3">
                            <h3><strong>REVENUE:</strong></h3>
                            <h2>₱ <span id="profit">0</span></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--begin::Entry-->
    <div class="row">
        <div class="col-12">
            <!--begin::Card-->
            <div class="card card-custom gutter-t">
                <div class="card-header">
                    <div class="card-title">
                        Sales Representation
                    </div>
                    <div class="card-tools">
                        <div class="btn-group">
                            <button type="button" class="btn btn-secondary dropdown-toggle dropdown-toggle-split"
                                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                Tools
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a href="" id="export_print_for_sr" class="dropdown-item">Print</a>
                                <a href="" id="export_copy_for_sr" class="dropdown-item">Copy</a>
                                <a href="" id="export_excel_for_sr" class="dropdown-item">Excel</a>
                                <a href="" id="export_csv_for_sr" class="dropdown-item">CSV</a>
                                <a href="" id="export_pdf_for_sr" class="dropdown-item">PDF</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-7">
                            <!--begin::Chart-->
                            <div id="chart_2"></div>
                            <!--end::Chart-->
                        </div>
                        <div class="col-lg-5">
                            <table class="table table-hover" id="tblPickedDates">
                                <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                </tr>
                                </thead>
                                <tbody>

                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
            <!--end::Card-->
        </div>
    </div>
    <div class="row">
        <div class="col">
            <div class="card card-custom gutter-t">
                <div class="card-header">
                    <div class="card-title">
                        Receipts
                    </div>
                    <div class="card-tools">
                        <div class="btn-group">
                            @if ($access->itms_read)
                                <button type="button" class="btn btn-secondary dropdown-toggle dropdown-toggle-split"
                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    Tools
                                    <span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    {{-- <div class="dropdown-divider"></div> --}}
                                    @include('admin.layouts.extra.dt_buttons')
                                    <div class="dropdown-divider"></div>
                                    <a href="{{route('print-label')}}" class="dropdown-item">Print Labels</a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div>
                        <h4 class="">Customers Served: <span id="served" class="badge badge-danger">0</span></h4>
                    </div>
                    <br>
                    <table class="table table-hover" id="tblReceipts">
                        <thead>
                        <td>SO #</td>
                        <td>Type</td>
                        <td>Terminal</td>
                        <td>Transacted by</td>
                        <td>Total</td>
                        <td>Time</td>
                        <td>Sale Type</td>
                        <td>Actions</td>
                        </thead>
                        {{-- <tbody id="receipts"> --}}
                        <tbody>
                        {{-- @foreach ($receipts as $item)
                            <tr>
                                <td>{{$item->son}}</td>
                                <td>
                                    @if ($item->type)
                                        <span class="text-danger">Refund</span>
                                    @else
                                        <span class="text-success">Sales</span>
                                    @endif
                                </td>
                                <td>{{$item->sold_by}}</td>
                                <td>₱ {{number_format($item->total, 2)}}</td>
                                <td>{{$item->terminal}}</td>
                                <td>{{date('M d, Y (h:i:s A)', strtotime($item->created_at))}}</td>
                                <td>
                                    <a href="{{route('show.receipts', $item->id)}}" class="btn btn-info "><i class="far fa-eye"></i></a>
                                </td>
                            </tr>
                        @endforeach --}}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('style')
    <link rel="stylesheet" href="{{ asset('plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
@endsection
@section('script')
    <script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
    <script src="{{ asset('plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
    <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
    <script src="{{ asset('plugins/sweetalert2/sweetalert2.min.js') }}"></script>
    
    <script src="{{ asset('dist/apex/apexcharts.js') }}"></script>
    {{-- <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script> --}}
    <script>
        $i = 0;
        $start = '{{Carbon\Carbon::today()->format("Y-m-d")}}';
        $end = '{{Carbon\Carbon::today()->format("Y-m-d")}}';
        $options = {
            serverside: true,
            processing: true,
            lengthChange: false,
            autoWidth: false,
            responsive: true,
            buttons: [
                'print',
                'copyHtml5',
                'excelHtml5',
                'csvHtml5',
                'pdfHtml5',
            ],
            columns: [
                {'data': 'son'},
                {'data': 'type'},
                {'data': 'terminal'},
                {'data': "sold_by"},
                {'data': 'total'},
                {'data': 'created_at'},
                {'data': 'sale_type'},
                {'data': 'updated_at'},
            ],
            columnDefs: [
                {
                    targets: 1,
                    render: function (data, type, full) {
                        var status = {
                            0: {'title': 'Sales', 'class': ' label-light-success'},
                            1: {'title': 'Refund', 'class': 'label-light-warning'},
                        };
                        return '<span class="label label-lg font-weight-bold' + status[full.type].class + ' label-inline">' + status[full.type].title + '</span>';
                    }
                },
                {
                    targets: -1,
                    title: "Actions",
                    orderable: false,
                    render: function (data, type, full) {
                        // return '<a href="receipts/'+full.id+'" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i></a> <a href="receipts/print/'+full.id+'" class="btn btn-info btn-sm"><i class="fas fa-print"></i></a>';
                        return '<a href="receipts/' + full.id + '" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i></a>';
                    }
                },
                {
                    targets: 6,
                    title: "Sale Type",
                    render: function (data, type, full) {
                        // console.log(full.sale_type);
                        // const types = ["senior", "pwd"];
                        let saleType = full.sale_type;
                        // if(types.indexOf(type) > 0){
                        //   type = "<span class='text-info'>" + type.toUpperCase() + "</span>";
                        // }else{
                        //   type = "<span class='text-success'>NORMAL</span>";
                        // }
                        // console.log(types.indexOf(type));
                        // return type;
                        if (saleType !== null) {
                            if (saleType.length > 1) {
                                saleType = "<span class='text-info'>" + type.toUpperCase() + "</span>";
                            } else {
                                saleType = "<span class='text-success'>NORMAL</span>";
                            }
                        } else {
                            saleType = "<span class='text-success'>NORMAL</span>";
                            ;
                        }
                        return saleType;
                    }
                },
                {
                    targets: 4,
                    render: function (data, type, full) {
                        $i++;
                        return full.total.toFixed(2);
                    }
                },
                {
                    targets: 5,
                    render: function (data, type, full) {
                        return moment(full.created_at).format('MMM-DD-YYYY (hh:mm A)');
                    }
                }
            ],
            ajax: {
                data: {
                    'start': function () {
                        return $start
                    },
                    'end': function () {
                        return $end
                    },
                    'user': {{auth()->user()->user_id}}
                },
                url: "{{route('api.sales-summary')}}"
            }
        };
        $(document).ready(function () {
            // For Date Selection
            $('#reservation').daterangepicker()
            $('#reservationdatePurhcased').datetimepicker({
                format: 'L'
            });
            $('#reservationdateExpect').datetimepicker({
                format: 'L'
            });
            $('input[name="daterange"]').daterangepicker({
                opens: 'left'
            }, function (start, end, label) {
                $start = start.format("YYYY-MM-DD");
                $end = end.format("YYYY-MM-DD");
                var table = $('#tblReceipts').DataTable().ajax.reload();
                var table2 = $('#tblPickedDates').DataTable().ajax.reload();
                getDetails();
            });

            getDetails();
            // Initialize DataTable tblPickedDates
            initTblPickedDates();
            loadTablesHTML();
            loadChartsHTML();
            $("#year_search").on("change", function (e) {
                $year = this.value;
                $("#tblMonthlySales").DataTable().ajax.reload();
            })
        });
        // DataTables beside the Charts
        // Initialize tblPickedDates
        function initTblPickedDates() {
            var table = $("#tblPickedDates");
            table.DataTable({
                responsive: true,
                serverside: true,
                processing: true,
                "scrollY": "300px",
                "scrollCollapse": true,
                "lengthChange": false,
                "autoWidth": false,
                buttons: [
                    'print',
                    'copyHtml5',
                    'excelHtml5',
                    'csvHtml5',
                    'pdfHtml5',
                ],
                columns: [
                    {"data": "x"},
                    {"data": "y"},
                ],
                ajax: {
                    data: {
                        'start': function () {
                            return $start
                        },
                        'end': function () {
                            return $end
                        },
                        'user': {{auth()->user()->user_id}},
                    },
                    dataSrc: function (response) {
                        console.log(response);
                        if (($start - $end) / 1000 / 60 / 60 / 24 == 0) {
                            $format = 'HH:mm A';
                        }
                        chart.updateSeries([{
                            name: 'Sales',
                            data: response.chart
                        }])
                        return response.data;
                    },
                    url: "{{route('api.sales-charts-table')}}",
                },
            });
            $('#export_print_for_sr').on('click', function (e) {
                e.preventDefault();
                table.button(0).trigger();
            });

            $('#export_copy_for_sr').on('click', function (e) {
                e.preventDefault();
                table.button(1).trigger();
            });

            $('#export_excel_for_sr').on('click', function (e) {
                e.preventDefault();
                table.button(2).trigger();
            });

            $('#export_csv_for_sr').on('click', function (e) {
                e.preventDefault();
                table.button(3).trigger();
            });

            $('#export_pdf_for_sr').on('click', function (e) {
                e.preventDefault();
                table.button(4).trigger();
            });
        }

        // Function for Getting the years available in the records
        $year = "{{Carbon\Carbon::now()->format('Y')}}";

        function loadYearDetails() {
            $.ajax({
                url: "{{route('api.get.sales-year')}}",
                success: function (response) {
                    $.each(response.output, function () {
                        // console.log(this.id);
                        $('#year_search').append('<option value=' + this.id + '>' + this.text + '</option>');
                        $year = this.id;
                        $('#bir_search').append('<option value=' + this.id + '>' + this.text + '</option>');
                    })
                },
                error: function (response) {
                    console.log(response);
                }
            });
        }

        function getDetails() {
            $.ajax({
                url: "{{route('api.sales-details')}}",
                data: {
                    'start': function () {
                        return $start
                    },
                    'end': function () {
                        return $end
                    },
                    'user': {{auth()->user()->user_id}}
                },
                success: function (data) {
                    // console.log(data);
                    $('#gross').html(data.total_gross);
                    $('#refunds').html(data.refunds);
                    $('#sales').html(data.gross_sales);
                    $('#profit').html(data.profit);
                    $('#served').html(data.served)
                },
                error: function (data) {
                    console.log(data);
                }
            });
        }

        function loadTablesHTML() {
            var table = $('#tblReceipts').DataTable($options);
            $('#export_print').on('click', function (e) {
                e.preventDefault();
                table.button(0).trigger();
            });

            $('#export_copy').on('click', function (e) {
                e.preventDefault();
                table.button(1).trigger();
            });

            $('#export_excel').on('click', function (e) {
                e.preventDefault();
                table.button(2).trigger();
            });

            $('#export_csv').on('click', function (e) {
                e.preventDefault();
                table.button(3).trigger();
            });

            $('#export_pdf').on('click', function (e) {
                e.preventDefault();
                table.button(4).trigger();
            });
        }

        // Shared Colors Definition
        const primary = '#6993FF';
        const success = '#1BC5BD';
        const info = '#8950FC';
        const warning = '#FFA800';
        const danger = '#F64E60';
        $format = 'dd/MM/yy HH:mm';
        $data = []
        $lineOptions = {
            series: [],
            chart: {
                height: 415,
                type: 'line',
                zoom: {
                    enabled: false
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth'
            },
            grid: {
                row: {
                    colors: ['#f3f3f3', 'transparent'], // takes an array which will be repeated on columns
                    opacity: 0.5
                },
            },
            yaxis: {
                labels: {
                    formatter: function (value) {
                        return "₱ " + value + "";
                    },
                },
            },
            noData: {text: "Loading..."},
            colors: [danger]
        }
        $apexOptions = {
            series: [],
            chart: {
                height: 415,
                type: 'area'
            },
            noData: {
                text: 'Loading...'
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth'
            },
            yaxis: {
                labels: {
                    trim: false,
                    formatter: function (value) {
                        return "₱ " + value.toFixed(2);
                    }
                },
                min: 0,
                decimalsInFloat: 2,
            },
            xaxis: {
                labels: {}
            },
            tooltip: {
                x: {
                    format: "'" + function () {
                        return $format
                    } + "'",
                },
            },
            colors: [info]
        };
        $apexBirOptions = {
            series: [],
            chart: {
                height: 415,
                type: 'area'
            },
            noData: {
                text: 'Loading...'
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth'
            },
            yaxis: {
                labels: {
                    trim: false,
                    formatter: function (value) {
                        return "₱ " + value.toFixed(2);
                    }
                },
                min: 0,
                decimalsInFloat: 2,
            },
            xaxis: {
                labels: {}
            },
            tooltip: {
                x: {
                    format: "'" + function () {
                        return $format
                    } + "'",
                },
            },
            colors: [warning]
        }
        const apexChart = "#chart_2";
        var chart = new ApexCharts(document.querySelector(apexChart), $apexOptions);

        function loadChartsHTML() {
            chart.render();
            // chartBir.render();
        }
    </script>
@endsection
