@extends('layout.app')
@section('header')
    - {{ $pos->name }} VAT Report
@endsection
@section('title')
    {{ $pos->name }} - VAT Report
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.bir.vat') }}">VAT Reports</a></li>
    <li class="breadcrumb-item text-muted">{{ $pos->name }}</li>
@endsection
@section('actions')
    <x-data-table.actions></x-data-table.actions>
    <x-general.search-table title="VAT Report"></x-general.search-table>
    <a href="{{ route('reports.bir.vat') }}" class="btn btn-light-primary btn-sm">
        <i class="ki-duotone ki-arrow-left fs-4"><span class="path1"></span><span class="path2"></span></i>
        Back
    </a>
@endsection
@section('content')
    <div class="card card-flush">
        <div class="card-header pt-6">
            <div class="card-title">
                <h3 class="fw-bold">{{ $pos->name }} - BIR VAT Report</h3>
            </div>
            <div class="card-toolbar">
                <span class="badge badge-light-info fs-7">{{ $zreadings->count() }} Z-Readings</span>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle gy-3 gs-4" id="table">
                    <thead>
                        <tr class="fw-bold text-uppercase fs-8 text-gray-500 bg-light">
                            <th class="min-w-100px" rowspan="2">Date</th>
                            <th class="text-center" rowspan="2">Beg SI#</th>
                            <th class="text-center" rowspan="2">End SI#</th>
                            <th class="text-end" rowspan="2">Acc. Begin Bal</th>
                            <th class="text-end" rowspan="2">Acc. End Bal</th>
                            <th class="text-end" rowspan="2">Gross Sales</th>
                            <th class="text-end" rowspan="2">VATable</th>
                            <th class="text-end" rowspan="2">VAT</th>
                            <th class="text-end" rowspan="2">VAT Exempt</th>
                            <th class="text-end" rowspan="2">Zero Rated</th>
                            <th class="text-center bg-light-warning" colspan="6">Deductions</th>
                            <th class="text-center bg-light-info" colspan="6">VAT Adjustments</th>
                            <th class="text-center bg-light-success" colspan="4">Summary</th>
                            <th class="text-center" rowspan="2">Reset#</th>
                            <th class="text-center" rowspan="2">Z#</th>
                        </tr>
                        <tr class="fw-bold text-uppercase fs-8 text-gray-500">
                            <th class="text-end bg-light-warning">SC</th>
                            <th class="text-end bg-light-warning">PWD</th>
                            <th class="text-end bg-light-warning">NAAC</th>
                            <th class="text-end bg-light-warning">Solo Parent</th>
                            <th class="text-end bg-light-warning">Refunds</th>
                            <th class="text-end bg-light-warning">Total</th>
                            <th class="text-end bg-light-info">SC</th>
                            <th class="text-end bg-light-info">PWD</th>
                            <th class="text-end bg-light-info">NAAC</th>
                            <th class="text-end bg-light-info">Solo Parent</th>
                            <th class="text-end bg-light-info">Returns</th>
                            <th class="text-end bg-light-info">Total</th>
                            <th class="text-end bg-light-success">VAT Payable</th>
                            <th class="text-end bg-light-success">Net Sales</th>
                            <th class="text-end bg-light-success">Overrun</th>
                            <th class="text-end bg-light-success">Total Income</th>
                        </tr>
                    </thead>
                    <tbody class="fs-7">
                        @foreach($zreadings as $reading)
                            @php
                                $totalDeductions = $reading->refund + $reading->sc_discount + $reading->pwd_discount + $reading->naac_discount + $reading->solo_parent_discount;
                                $totalVatAdjustment = $reading->sc_vat_adjustment + $reading->pwd_vat_adjustment + $reading->naac_vat_adjustment + $reading->sp_vat_adjustment + $reading->vat_on_refunds;
                                $vatPayable = $reading->vatable - $totalVatAdjustment;
                                $netSales = ($reading->vat_exempt - $reading->zero_rated) - $totalDeductions;
                            @endphp
                            <tr>
                                <td class="text-nowrap">{{ \Carbon\Carbon::parse($reading->created_at)->format('M d, Y h:i A') }}</td>
                                <td class="text-center">{{ $reading->first_or }}</td>
                                <td class="text-center">{{ $reading->last_or }}</td>
                                <td class="text-end text-nowrap">{{ number_format($reading->previous_accumulated_sales, 2) }}</td>
                                <td class="text-end text-nowrap">{{ number_format($reading->present_accumulated_sales, 2) }}</td>
                                <td class="text-end text-nowrap fw-semibold">{{ number_format($reading->net_sales, 2) }}</td>
                                <td class="text-end text-nowrap">{{ number_format($reading->vatable, 2) }}</td>
                                <td class="text-end text-nowrap">{{ number_format($reading->vat, 2) }}</td>
                                <td class="text-end text-nowrap">{{ number_format($reading->vat_exempt, 2) }}</td>
                                <td class="text-end text-nowrap">{{ number_format($reading->zero_rated, 2) }}</td>
                                {{-- Deductions --}}
                                <td class="text-end text-nowrap">{{ number_format($reading->sc_discount, 2) }}</td>
                                <td class="text-end text-nowrap">{{ number_format($reading->pwd_discount, 2) }}</td>
                                <td class="text-end text-nowrap">{{ number_format($reading->naac_discount, 2) }}</td>
                                <td class="text-end text-nowrap">{{ number_format($reading->solo_parent_discount, 2) }}</td>
                                <td class="text-end text-nowrap">{{ number_format($reading->refund, 2) }}</td>
                                <td class="text-end text-nowrap fw-semibold">{{ number_format($totalDeductions, 2) }}</td>
                                {{-- VAT Adjustments --}}
                                <td class="text-end text-nowrap">{{ number_format($reading->sc_vat_adjustment, 2) }}</td>
                                <td class="text-end text-nowrap">{{ number_format($reading->pwd_vat_adjustment, 2) }}</td>
                                <td class="text-end text-nowrap">{{ number_format($reading->naac_vat_adjustment, 2) }}</td>
                                <td class="text-end text-nowrap">{{ number_format($reading->sp_vat_adjustment, 2) }}</td>
                                <td class="text-end text-nowrap">{{ number_format($reading->vat_on_refunds, 2) }}</td>
                                <td class="text-end text-nowrap fw-semibold">{{ number_format($totalVatAdjustment, 2) }}</td>
                                {{-- Summary --}}
                                <td class="text-end text-nowrap fw-semibold">{{ number_format($vatPayable, 2) }}</td>
                                <td class="text-end text-nowrap fw-semibold">{{ number_format($netSales, 2) }}</td>
                                <td class="text-end text-nowrap">0.00</td>
                                <td class="text-end text-nowrap fw-bold">{{ number_format($netSales, 2) }}</td>
                                <td class="text-center">{{ $reading->reset_counter }}</td>
                                <td class="text-center">{{ $reading->counter }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
@section('vendor-styles')
    <link rel="stylesheet" href="{{ asset("assets/plugins/custom/datatables/datatables.bundle.css") }}">
@endsection
@section('vendor-scripts')
    <script src="{{ asset("assets/plugins/custom/datatables/datatables.bundle.js") }}"></script>
@endsection
@section('scripts')
    <script>
        $(document).ready(function () {
            var table = $('#table');
            let dataTable = table.DataTable({
                paging: false,
                ordering: true,
                order: [[0, 'asc']],
                scrollX: true,
            });

            const documentTitle = '{{ $pos->name }} - BIR VAT Report';
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
                        title: documentTitle,
                        orientation: 'landscape',
                        pageSize: 'LEGAL'
                    }
                ]
            }).container().appendTo($('#datatable_buttons'));

            const exportButtons = document.querySelectorAll('#datatables_menu [data-kt-export]');
            exportButtons.forEach(exportButton => {
                exportButton.addEventListener('click', e => {
                    e.preventDefault();
                    const exportValue = e.target.getAttribute('data-kt-export');
                    const target = document.querySelector('.dt-buttons .buttons-' + exportValue);
                    target.click();
                });
            });

            $('#tableSearch').keyup(function () {
                dataTable.search($(this).val()).draw();
            });
        });
    </script>
@endsection
