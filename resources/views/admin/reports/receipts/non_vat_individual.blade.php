@extends('admin.printer.default')
@section('title')
    Non-VAT Report | {{Carbon\Carbon::parse($qDate)->startOfMonth()->format("M, Y")}}
@endsection
@section('style')
    <link rel="stylesheet" href="{{ asset('plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="d-flex justify-content-center">
                <h3>Non-VAT Report</h3>
            </div>
            <div class="d-flex justify-content-center">
                <h5>for</h5>
            </div>
            <div class="d-flex justify-content-center">
                <h2>{{Carbon\Carbon::parse($qDate)->startOfMonth()->format("M, Y")}}</h2>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col">
            <div class="d-flex justify-content-center">
                <div class="btn-group">
                    <button type="button" class="btn btn-secondary dropdown-toggle dropdown-toggle-split"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Tools
                        <span class="sr-only">Toggle Dropdown</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        {{-- <div class="dropdown-divider"></div> --}}
                        @include('admin.layouts.extra.dt_buttons')
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col">
            <table class="table table-hover" id="table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Terminal #</th>
                    <th>Sales</th>
                    <th>Refunds</th>
                    <th>Sales Non-VAT</th>
                    <th>Refunds Non-VAT</th>
                    <th>Non-VAT Payable</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($q as $item)
                    <tr>
                        <td>{{$item->day}}</td>
                        <td>{{$item->terminal}}</td>
                        <td>{{$item->sales}}</td>
                        <td>{{$item->refunds}}</td>
                        <td>{{$item->snon_vat}}</td>
                        <td>{{$item->rnon_vat}}</td>
                        <td>{{$item->net_non_vat}}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

@endsection
@section('script')
    <!-- jQuery -->
    <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
    <!-- Bootstrap -->
    <script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    {{-- DataTable --}}
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
    <script>
        $(function () {
            var table = $("#table").DataTable({
                scrollX: false,
                paging: false,
                "lengthChange": false,
                "autoWidth": false,
                responsive: true,
                buttons: [
                    'print',
                    'copyHtml5',
                    'excelHtml5',
                    'csvHtml5',
                    'pdfHtml5',
                ],
            });
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
        });
    </script>
@endsection
