@extends('admin.printer.default')
@section('title')
    Terminal #: {{$pos->number}} | Date: {{$dateSelected}}
@endsection
@section('style')
    <link rel="stylesheet" href="{{ asset('plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-center">
                <h4>{{$pos->owner->name}}</h4>
            </div>
            <div class="d-flex justify-content-center">
                <h5>{{$pos->store->name}}</h5>
            </div>
            <div class="d-flex justify-content-center">
                <h5>{{$pos->owner->details->address}}</h5>
            </div>
            <div class="d-flex justify-content-center">
                <h5>TIN: {{$pos->store->tin}}</h5>
            </div>
            <div class="d-flex justify-content-center">
                <h5>Terminal #: {{$pos->number}}</h5>
            </div>
            <div class="d-flex justify-content-center">
                <h5>MIN: {{$pos->min}}</h5>
            </div>
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
            <table class="table table-striped" id="table">
                <thead>
                <tr>
                    <th>Date (mm/dd/yy)</th>
                    <th>Branch</th>
                    <th>Terminal</th>
                    <th>MIN</th>
                    <th>Beginning SI/OR No.</th>
                    <th>Ending SI/OR No.</th>
                    <th>Grand Accum. Sales Ending Balance</th>
                    <th>Grand Accum. Sales Beginning Balance</th>
                    <th>Gross Sales for the Day</th>
                    <th><small>Sales Issued with Manual SI/OR (per RR 16-2018)</small></th>
                    <th>Gross Sales From POS</th>
                    <th>VATable Sales</th>
                    <th>VAT Amount</th>
                    <th>VAT-Exempt Sales</th>
                    <th>Zero Rated Sales</th>
                    <th>Regular Discounts</th>
                    <th>Special Discount (SC/PWD)</th>
                    <th>Returns</th>
                    <th>Void</th>
                    <th>Total Deductions</th>
                    <th>VAT on Special Discounts</th>
                    <th>VAT on Returns</th>
                    <th>Others</th>
                    <th>Total VAT Adj.</th>
                    <th>VAT Payable</th>
                    <th>Net Sales</th>
                    <th>Other Income</th>
                    <th>Sales Overrun/Overflow</th>
                    <th>Total Net Sales</th>
                    <th>Reset Counter</th>
                    <th>Remarks</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($query as $item)
                    <tr>
                        {{--                         Date--}}
                        <td>{{$item->date}}</td>
                        {{--                        Branch--}}
                        <td>{{ $item->store_name }}</td>
                        {{--                        Terminal--}}
                        <td>{{ $item->terminal_name }}</td>
                        {{--                        MIN--}}
                        <td>{{ $item->min }}</td>
                        {{--                         Beginning SI/OR No.--}}
                        <td>{{$item->start_son}}</td>
                        {{--                         Ending SI/OR No.--}}
                        <td>{{$item->end_son}}</td>
                        {{--                         Grand Accumulating Sales Ending Balance--}}
                        <td>
                            {{number_format($item->end_acc, 2)}}
                        </td>
                        {{--                         Grand Accumulating Sales Beginning Balance--}}
                        <td>
                            {{number_format($item->start_acc, 2)}}
                        </td>
                        {{--                         Gross Sales for the Day--}}
                        <td>{{number_format($item->gross, 2)}} </td>
                        {{--                         Sales Issued with Manual SI/OR (per RR 16-2018)--}}
                        <td>0</td>
                        {{--                         Gross Sales From POS--}}
                        <td>{{number_format($item->gross, 2)}}</td>
                        {{--                         VATable Sales--}}
                        <td>{{number_format($item->vatable, 2)}}</td>
                        {{--                         VAT Amount--}}
                        <td>{{number_format($item->vat, 2)}}</td>
                        {{--                         VAT-Exempt Sales--}}
                        <td>{{number_format($item->vat_exempt, 2)}}</td>
                        {{--                         Zero Rated Sales--}}
                        <td>{{number_format($item->zero_rated, 2)}}</td>
                        {{--                         Regular Discounts--}}
                        <td>{{number_format(($item->discount) ? $item->discount : 0, 2)}}</td>
                        {{--                         Special Discounts--}}
                        <td>{{number_format($item->sp_discount, 2)}}</td>
                        {{--                         Refunds/Returns--}}
                        <td>{{number_format($item->refund, 2)}}</td>
                        {{--                         Void Transactions--}}
                        <td>0</td>
                        {{--                         Total Deductions--}}
                        <td>{{number_format($item->refund, 2)}}</td>
                        {{--                         VAT on special discounts--}}
                        <td>{{number_format($item->sp_vat, 2)}}</td>
                        {{--                         VAT on Returns--}}
                        <td>{{number_format($item->r_vat, 2)}}</td>
                        {{--                         Others--}}
                        <td>0</td>
                        {{--                         Total VAT Adj.--}}
                        <td>
                            @php
                                $ttlAdjVat = $item->sp_vat + $item->r_vat + 0;
                            @endphp
                            {{number_format($ttlAdjVat, 2)}}
                        </td>
                        {{--                         VAT Payable--}}
                        <td>{{number_format($item->vat - $ttlAdjVat, 2)}}</td>
                        {{--                         NET Sales--}}
                        <td>
                            @php
                                $netSales = $item->gross - $item->refund - $item->vatable - $item->r_vat
                            @endphp
                            {{number_format($netSales, 2)}}
                        </td>
                        {{--                         Other Income--}}
                        <td>0</td>
                        {{--                         Sale Overrun/Overflow--}}
                        <td>0</td>
                        {{--                         Total Net Sales--}}
                        <td>{{$netSales}}</td>
                        {{--                         Reset Counter--}}
                        <td>N/A</td>
                        {{--                         Remarks--}}
                        <td>-</td>
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
                scrollX: true,
                paging: false,
                scrollY: "500px",
                "lengthChange": true,
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
