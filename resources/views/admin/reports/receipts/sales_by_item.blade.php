@extends('admin.layouts.master')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Sales By Item</li>
@endsection
@section('title')
    Sales By Item
@endsection
@section('content')
    <div class="row">
        <div class="col-md-4">
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
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    Top 10 Items/Products
                </div>
                <div class="card-body">
                    <canvas id="pieChart"
                            style="min-height: 250px; height: 400px; max-height: 550px; max-width: 100%;"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table class="table table-hover" id="tblroles">
                        <thead>
                        <tr>
                            <th>Item</th>
                            <th>SON</th>
                            <th>QTY</th>
                            <th>UNIT</th>
                            <th>UNIT_QTY</th>
                            <th>Discount</th>
                            <th>Cost</th>
                            <th>Price</th>
                            <th>Sub-Total</th>
                            <th>Profit</th>
                            <th>Vatable</th>
                        </tr>
                        </thead>
                        <tbody>
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
    
    <script>
        $(document).ready(function () {
            $('#reservationdatePurhcased').datetimepicker({
                format: 'L'
            });
            $('#reservationdateExpect').datetimepicker({
                format: 'L'
            });
        })
    </script>
    <script>
        $(function () {
            $start = "{{ Carbon\Carbon::today()->startOfDay()->toDateTimeString() }}";
            $end = "{{ Carbon\Carbon::today()->endOfDay()->toDateTimeString() }}";
            $('input[name="daterange"]').daterangepicker();
            $('input[name="daterange"]').daterangepicker({
                    opens: 'left'
                }, function (start, end, label) {
                    $start = start.format("YYYY-MM-DD");
                    $end = end.format("YYYY-MM-DD");
                    console.log($end);
                    $("#tblroles").DataTable().ajax.reload();
                    $.ajax({
                        type: 'get',
                        // url : '{{route("get.selected.date.receipts")}}',
                        url: '{{route("sold-items")}}',
                        data: {
                            'start': function () {
                                return $start
                            }, 'end': function () {
                                return $start
                            }
                        },
                        success: function (data) {
                            pieChart.data.datasets[0].data = data.item_total;
                            pieChart.data.labels = data.item_name;
                            pieChart.update();
                        },
                        error: function (data) {
                            console.log(data);
                        }
                    });
                }
            );

            $option = {
                dom: 'lBfrtip',
                "responsive": true,
                "lengthChange": false,
                "autoWidth": false,
                serverside: true,
                processing: true,
                "buttons": ["excel", "print"],
                ajax: {
                    url: "{{ route('api.sales.item') }}",
                    data: {
                        'user': {{ auth()->user()->user_id }},
                        'start': function () {
                            return $start
                        },
                        'end': function () {
                            return $end
                        },
                    },
                    dataSrc: function (response) {
                        return response.data;
                    },
                },
                columns: [
                    {'data': 'item_name'},
                    {'data': 'son'},
                    {'data': 'qty'},
                    {'data': 'unit'},
                    {'data': 'unit_qty'},
                    {'data': 'discount'},
                    {'data': 'cost'},
                    {'data': 'price'},
                    {'data': 'sub_total'},
                    {'data': 'profit'},
                    {'data': 'vatable'},
                ]
            }
            // $("#tblroles").DataTable().buttons().container().appendTo('#tblroles_wrapper .col-md-6:eq(0)');
            $("#tblroles").DataTable($option);

            var donutData = {
                labels:
                //   data here
                        {!!$item_name!!}
                ,
                datasets: [
                    {
                        data:
                        // data here
                                {!!$item_total!!}
                        ,
                        backgroundColor: ['#f56954', '#00a65a', '#f39c12', '#00c0ef', '#3c8dbc', '#d2d6de', '#6610f2', '#dc3545', '#17a2b8', '#20c997'],
                    }
                ]
            }
            //-------------
            //- PIE CHART -
            //-------------
            // Get context with jQuery - using jQuery's .get() method.
            var pieChartCanvas = $('#pieChart').get(0).getContext('2d')
            var pieData = donutData;
            var pieOptions = {
                maintainAspectRatio: false,
                responsive: true,
            }
            //Create pie or douhnut chart
            // You can switch between pie and douhnut using the method below.
            var pieChart = new Chart(pieChartCanvas, {
                type: 'pie',
                data: pieData,
                options: pieOptions,
            })
        })
    </script>
@endsection
