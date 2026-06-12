@extends('admin.layouts.master')
@section('title')
    Readings
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">VAT Report</li>
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">X and Z Readings</h3>
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
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label><h3>Select Date</h3></label>

                                <div class="input-group">
                                    <div class="input-group-prepend">
                                    <span class="input-group-text">
                                      <i class="far fa-calendar-alt"></i>
                                    </span>
                                    </div>
                                    <input type="text" class="form-control float-right" id="reservation"
                                           name="daterange">
                                </div>
                                <!-- /.input group -->
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <table id="tblReadings" class="table table-hover">
                                <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Store</th>
                                    <th>Terminal</th>
                                    <th>Counter</th>
                                    <th>Transactions</th>
                                    <th>Gross</th>
                                    <th>Refunds</th>
                                    <th>Net</th>
                                    <th>Employee</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>

                                </tbody>
                            </table>
                        </div>
                    </div>
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
    <style>

    </style>
@endsection
@section('script')
    <script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
    <script src="{{ asset('plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
    
    <script>
        $start = '{{Carbon\Carbon::today()->format("Y-m-d")}}';
        $end = '{{Carbon\Carbon::today()->format("Y-m-d")}}';
        $option = {
            "lengthChange": false,
            "autoWidth": false,
            "responsive": true,
            serverside: true,
            processing: true,
            buttons: [
                'print',
                'copyHtml5',
                'excelHtml5',
                'csvHtml5',
                'pdfHtml5',
            ],
            ajax: {
                url: "{{route('readings')}}",
                data: {
                    'start': function () {
                        return $start
                    },
                    'end': function () {
                        return $end
                    },
                    user: {{auth()->user()->user_id}}
                }
            },
            columns: [
                {'data': 'type'},
                {'data': 'store'},
                {'data': 'terminal'},
                {'data': 'counter'},
                {'data': 'transactions'},
                {'data': 'gross'},
                {'data': 'refunds'},
                {'data': 'net'},
                {'data': 'employee'},
                {'data': 'date'},
                {'data': 'id'},
            ],
            columnDefs: [
                {
                    targets: 0,
                    width: 50,
                },
                {
                    targets: 2,
                    width: 100,
                },
                {
                    targets: 3,
                    width: 100,
                },
                {
                    targets: 4,
                    width: 100,
                },
                {
                    targets: -1,
                    title: 'Actions',
                    orderable: false,
                    render: function (data, type, full) {
                        // console.log(full.type + " " + full.id);
                        return '\
                            <a href="readings/' + full.type + '/' + full.id + '" class="btn btn-sm btn-primary" title="View details">\
                                <i class="far fa-eye"></i>\
                            </a>\
                            ';
                    }
                }
            ]
        };
        $(function () {
            $('input[name="daterange"]').daterangepicker({
                opens: 'left'
            }, function (start, end, label) {
                $start = start.format("YYYY-MM-DD");
                $end = end.format("YYYY-MM-DD");
                var table = $('#tblReadings').DataTable().ajax.reload();

            });

            loadDataTable();
        });

        function loadDataTable() {
            var table = $("#tblReadings").DataTable($option);
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
    </script>
@endsection
