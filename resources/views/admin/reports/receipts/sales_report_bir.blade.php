@extends('admin.layouts.master')
@section('title')
    Sales Report - BIR
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Sales Report BIR</li>
@endsection
@section('content')
    {{-- Display Data --}}
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        BIR Sales Report
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="">Select Year</label>
                        <select class="form-control select2" id="year_search" name="param">
                            @foreach ($years as $year)
                                @if ($year->year != 0)
                                    <option>{{$year->year}}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="month">Select Month</label>
                        <select name="month" id="month" class="form-control">
                            @php
                                $date = Carbon\Carbon::now()->startOfYear();
                                for($i = 0; $i < 12; $i++){
                                    $selected = "";
                                    if($date->format("M") == Carbon\Carbon::now()->format("M")){
                                        $selected = "selected";
                                    }
                                     echo "<option $selected>".$date->format("F")."</option>";
                                     $date->addMonths(1);
                                }
                            @endphp
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-info" id="btn_submit">Generate Report</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header border-0">
                    <div class="card-title">
                        Monthly Report <span class="badge badge-danger">
                        <span id="selected_month"></span>, <span id="selected_year"></span>
                    </span>
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
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-hover" id="tblRecords">
                        <thead>
                        <tr>
                            <th>POS Name</th>
                            <th>Terminal #</th>
                            <th>Store Name</th>
                            <th>Branch #</th>
                            <th>Month</th>
                            <th>Year</th>
                            <th>NET</th>
                            <th>VATable</th>
                            <th>VAT</th>
                            <th>Zero Rated</th>
                            <th>VAT Exempt</th>
                            <th>Revenue</th>
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
    <script>
        $(document).ready(function () {
            // For Date Selection
            // loadYearDetails();
            $('#year_search').select2({
                placeholder: "Select year",
                allowClear: true,
            });
            $('#month').select2({
                placeholder: "Select month",
                allowClear: true,
            });
            $("#year_search").on("change", function () {
                $year = this.value;
                console.log($year);
            });
            $("#month").on("change", function () {
                $month = this.value;
            })
            $("#btn_submit").on("click", function () {
                $("#tblRecords").DataTable().ajax.reload();
                $("#tblRecords").DataTable().columns.adjust().draw();
                $("#selected_month").text($month);
                $("#selected_year").text($year);
            });
            loadRecordTbl();
            var month = $("#selected_month").text(function () {
                return $month
            });
            var year = $("#selected_year").text(function () {
                return $year
            });
        });
        $start = '{{Carbon\Carbon::today()->format("Y-m-d")}}';
        $end = '{{Carbon\Carbon::today()->format("Y-m-d")}}';
        $year = '{{Carbon\Carbon::today()->format("Y")}}'
        $month = '{{Carbon\Carbon::today()->startOfMonth()->format("M")}}';
        $options = {
            responsive: true,
            serverside: true,
            processing: true,
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
                {'data': 'name'},
                {'data': 'terminal'},
                {'data': "store"},
                {'data': "branch"},
                {'data': "sale_month"},
                {'data': "sale_year"},
                {'data': 'total'},
                {'data': 'vatable'},
                {'data': 'vat'},
                {'data': 'zero_rated'},
                {'data': "non_vat"},
                {'data': 'revenue'},
                // {'data': 'terminal'},
                // {'data': 'created_at'},
                // {'data': 'updated_at'},
            ],
            columnDefs: [
                {
                    targets: 4,
                    render: function (data, type, full) {
                        var month = "";
                        switch (full.sale_month) {
                            case(1):
                                month = "January";
                                break;
                            case(2):
                                month = "February";
                                break;
                            case(3):
                                month = "March";
                                break;
                            case(4):
                                month = "April";
                                break;
                            case(5):
                                month = "May";
                                break;
                            case(6):
                                month = "June";
                                break;
                            case(7):
                                month = "July";
                                break;
                            case(8):
                                month = "August";
                                break;
                            case(9):
                                month = "September";
                                break;
                            case(10):
                                month = "October";
                                break;
                            case(11):
                                month = "November";
                                break;
                            case(12):
                                month = "December";
                                break;
                        }
                        return month;
                    }
                },
                {
                    targets: 12,
                    title: 'Actions',
                    render: function (data, type, full) {
                        return '\
                        <form type="GET" action="{{route("bir.report.individual")}}" id="form_print_' + full.pos_id + '" target="_blank">\
                        <input type="hidden" name="pos_id" value="' + full.pos_id + '"/>\
                        <input type="hidden" name="year" value="' + $year + '"/>\
                        <input type="hidden" name="month" value="' + $month + '"/>\
                        <input type="hidden" name="user" value="{{auth()->user()->user_id}}"/>\
                        </form>\
                        <button type="submit" form="form_print_' + full.pos_id + '" class = "btn btn-sm btn-warning">\
                            <i class="fas fa-print"></i>\
                        </a>\
                        ';
                    }
                },
            ],
            ajax: {
                data: {
                    'year': function () {
                        return $year
                    },
                    'user': {{auth()->user()->user_id}},
                    'month': function () {
                        return $month
                    },
                },
                url: "{{route('api.bir-sales-monthly')}}"
            }
        };

        function loadRecordTbl() {
            var table = $('#tblRecords').DataTable($options);
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
    </script>
@endsection
