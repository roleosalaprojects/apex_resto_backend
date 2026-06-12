@extends('superadmin.layouts.master')
@section('title')
    Readings Adjustment
@endsection
@section('content')
    <div class="card card-outline card-danger">
        {{-- <div class="card-header">
            <h3 class="card-title"></h3>
        </div> --}}
        <div class="card-body">
            <div class="form-group">
                <label>Date range:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text">
                            <i class="far fa-calendar-alt"></i>
                        </span>
                    </div>
                    <input type="text" class="form-control float-right" id="reservation">
                </div>
            </div>
            <table class="table table-hover" id="readingsTable">
                <thead>
                <tr>
                    <th>Type</th>
                    <th>Terminal</th>
                    <th>Gross</th>
                    <th>Refunds</th>
                    <th>Net</th>
                    <th>VATable</th>
                    <th>VAT</th>
                    <th>Non VAT</th>
                    <th>Excess VATable</th>
                    <th>Excess VAT</th>
                    <th>Excess Non VAT</th>
                </tr>
                </thead>
                <tbody>

                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                Select Adjustments
            </h3>
            <div class="card-tools">
                <button type="button" id="btnAdjust" class="btn btn-outline-danger">Adjust</button>
            </div>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">
                    Set <i> VAT </i> Percentage to be deducted
                </label>
                <input type="number" class="form-control" name="vat_rate" min="1">
            </div>
            <div class="form-group">
                <label class="form-label">
                    Set <i> VAT Exempt </i> Percentage to be deducted
                </label>
                <input type="number" class="form-control" name="non_vat_rate" min="1">
            </div>
            <div class="form-group">
                <label class="form-label">
                    Set <i> Zero Rated </i> Percentage to be deducted
                </label>
                <input type="number" class="form-control" name="zero_rated_rate" min="1">
            </div>
        </div>
    </div>
@endsection
@section('style')
    <link rel="stylesheet" href="{{ asset('plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
    {{-- Data Tables --}}
    <link rel="stylesheet" href="{{ asset('plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">
@endsection
@section('script')
    <script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
    <script src="{{ asset('plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
    <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
    <script src="{{ asset('plugins/sweetalert2/sweetalert2.min.js') }}"></script>
    {{-- Data Tables --}}
    <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.bootstrap4.min.js') }}"></script>

    <script src="{{ asset('plugins/jszip/jszip.min.js') }}"></script>
    <script src="{{ asset('plugins/pdfmake/pdfmake.min.js') }}"></script>
    <script src="{{ asset('plugins/pdfmake/vfs_fonts.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.print.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.colVis.min.js') }}"></script>
    <script src="{{ asset('plugins/chart.js/Chart.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            var table = $("#readingsTable");
            $start = '{{Carbon\Carbon::today()->format("Y-m-d")}}';
            $end = '{{Carbon\Carbon::today()->format("Y-m-d")}}'
            let btnAdjust = $("#btnAdjust");
            $options = {
                serverside: true,
                processing: true,
                lengthChange: false,
                autoWidth: false,
                responsive: true,
                columns: [
                    {'data': 'type'},
                    {'data': 'terminal'},
                    {'data': 'gross'},
                    {'data': 'refunds'},
                    {'data': 'net'},
                    {'data': 'vatable'},
                    {'data': 'vat'},
                    {'data': 'non_vat'},
                    {'data': 'excess_vatable'},
                    {'data': 'excess_vat'},
                    {'data': 'excess_non_vat'},
                ],
                ajax: {
                    data: {
                        'startDate': function () {
                            return $start
                        },
                        'endDate': function () {
                            return $end
                        },
                    },
                    dataSrc: function (response) {
                        return response.data;
                    },
                    url: "{{route('superadmin.adjustment.readings')}}"
                }
            };
            table.DataTable($options);
            let daterangePicker = $('#reservation');
            daterangePicker.daterangepicker();

            // Event Hndlers
            daterangePicker.daterangepicker({
                opens: 'left',
                startDate: moment($start),
                endDate: moment($end),
            }, function (start, end, label) {
                $start = start.format("YYYY-MM-DD");
                $end = end.format("YYYY-MM-DD");
                table.DataTable().ajax.reload();
            });
            btnAdjust.on("click", function () {
                let vat_rate = $("input[name='vat_rate']").val(),
                    non_vat_rate = $("input[name='non_vat_rate']").val(),
                    zero_rated_rate = $("input[name='zero_rated_rate']").val();
                $.ajax({
                    method: "POST",
                    data: {
                        'startDate': function () {
                            return $start
                        },
                        'endDate': function () {
                            return $end
                        },
                        'vr': vat_rate,
                        'nvr': non_vat_rate,
                        'zrr': zero_rated_rate,
                    },
                    url: "{{ route('superadmin.adjustment.readings.adjust') }}",
                    success: function (response) {
                        alert(response)
                    },
                    error: function (response) {
                        console.log(response);
                    }
                });
            });
        })
    </script>
@endsection
