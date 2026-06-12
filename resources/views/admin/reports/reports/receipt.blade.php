@extends('layout.app')
@section('header')
    - View Receipt
@endsection
@section('title')
    SON #: {{ $sale->son }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a href="" class="">Dashboard</a></li>
    <li class="breadcrumb-item  text-muted"><span class="">Reports</span></li>
    <li class="breadcrumb-item "><a href="{{ route('reports.receipts') }}" class="">Sales Summary</a></li>
    <li class="breadcrumb-item text-muted">View Receipt</li>
@endsection
@section('actions')
    <a href="{{ route('receipt.print', $sale->id) }}" class="btn btn-light-info" target="_blank" rel="noopener noreferrer">Print</a>
@endsection
@section('content')
    <div class="card card-flush">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-7">
                <span class="fs-2 fw-bold">Invoice</span>
                <div>
                    <div class="text-sm-end">
                        <span class="fs-2 fw-bolder">{{ $sale->store->name }}</span>
                    </div>
                    <br>
                    <span class="fs-5 fw-semibold text-muted">{{ $sale->store->header }}</span>
                    <br>
                    <span class="fs-5 fw-semibold text-muted">TIN: {{ $sale->store->tin }}</span>
                    <br>
                    <span class="fs-5 fw-semibold text-muted">{{ $sale->store->email }}</span>
                    <br>
                    {{-- Web-admin cashless sales have pos_id = NULL (no
                         terminal involved). Show MIN/Serial only when a
                         POS terminal actually rang the sale; otherwise
                         label the sale as Web Admin-recorded so the
                         receipt makes sense to the reader. --}}
                    @if ($sale->pos)
                        <span class="fs-5 fw-semibold text-muted">MIN: {{ $sale->pos->min }}</span>
                        <br>
                        <span class="fs-5 fw-semibold text-muted">Serial: {{ $sale->pos->serial }}</span>
                    @else
                        <span class="fs-5 fw-semibold text-muted">Recorded via Web Admin</span>
                    @endif
                </div>
            </div>
            <div class="separator"></div>
            <div class="d-flex justify-content-between flex-column flex-md-row">
                <!--begin::Content-->
                <div class="flex-grow-1 pt-8 mb-13">
                    <!--begin::Table-->
                    <div class="table-responsive border-bottom mb-14">
                        <table class="table">
                            <thead>
                                <tr class="border-bottom fs-6 fw-bold text-muted text-uppercase">
                                    <th class="min-w-175px pb-9">Description</th>
                                    <th class="min-w-70px pb-9 text-end">Quantity</th>
                                    <th class="min-w-80px pb-9 text-end">Unit (PC)</th>
                                    <th class="min-w-80px pb-9 text-end">Price</th>
                                    <th class="min-w-100px pe-lg-6 pb-9 text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sale->lines as $line)
                                    <tr class="fw-bold text-gray-700 fs-5 text-end">
                                        <td class="d-flex align-items-center pt-11">
                                            {{ $line->item->name }}
                                        </td>
                                        <td class="pt-11">
                                            {{ number_format($line->qty, 0) }}
                                        </td>
                                        <td class="pt-11">
                                            {{ ($line->unit_id) ? $line->unit : "PC" }} ({{ $line->unit_qty }})
                                        </td>
                                        <td class="pt-11">
                                            {{ number_format($line->price - $line->discount, 2) }}
                                        </td>
                                        <td class="pt-11 fs-5 pe-lg-6 text-dark fw-bolder">
                                            ₱ {{ number_format($line->sub_total, 2) }}
                                        </td>
                                    </tr>  
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <!--end::Table-->
                    <div class="d-flex flex-row">
                        <!--begin::Section-->
                        <div class="d-flex flex-column mw-md-300px w-100">
                            <!--begin::Label-->
                            <div class="fw-semibold fs-5 mb-3 text-dark00">CASH</div>
                            <!--end::Label-->
                            <!--begin::Item-->
                            <div class="d-flex flex-stack text-gray-800 mb-3 fs-6">
                                <!--begin::Accountname-->
                                <div class="fw-semibold pe-5">Cash:</div>
                                <!--end::Accountname-->
                                <!--begin::Label-->
                                <div class="text-end fw-norma">₱ {{ number_format($sale->cash, 2) }}</div>
                                <!--end::Label-->
                            </div>
                            <!--end::Item-->
                            <!--begin::Item-->
                            <div class="d-flex flex-stack text-gray-800 fs-6">
                                <!--begin::Code-->
                                <div class="fw-semibold pe-5">Change:</div>
                                <!--end::Code-->
                                <!--begin::Label-->
                                <div class="text-end fw-norma">₱ {{ number_format($sale->change, 2) }}</div>
                                <!--end::Label-->
                            </div>
                            <!--end::Item-->
                        </div>
                        <!--end::Section-->
                        <div class="d-flex flex-column mw-md-500px w-100"></div>
                        <!--begin::Section-->
                        <div class="d-flex flex-column mw-md-500px w-100">
                            <!--begin::Label-->
                            <div class="fw-semibold fs-5 mb-3 text-dark00">{{ $supplier->name }}</div>
                            <!--end::Label-->
                            <!--begin::Item-->
                            <div class="d-flex flex-stack text-gray-800 fs-6">
                                <!--begin::Accountname-->
                                <div class="fw-semibold pe-5">{{ $supplier->header }}</div>
                                <!--end::Accountname-->
                            </div>
                            <!--end::Item-->
                            <!--begin::Item-->
                            <div class="d-flex flex-stack text-gray-800 fs-6">
                                <!--begin::Accountname-->
                                <div class="fw-semibold pe-5">TIN: {{ $supplier->tin }}</div>
                                <!--end::Accountname-->
                            </div>
                            <!--end::Item-->
                            <!--begin::Item-->
                            <div class="d-flex flex-stack text-gray-800 fs-6">
                                <!--begin::Accountname-->
                                <div class="fw-semibold pe-5">Email: {{ $supplier->email }}</div>
                                <!--end::Accountname-->
                            </div>
                            <!--end::Item-->
                            <!--begin::Item-->
                            <div class="d-flex flex-stack text-gray-800 fs-6">
                                <!--begin::Accountname-->
                                <div class="fw-semibold pe-5">PTU: {{ $supplier->ptu }}</div>
                                <!--end::Accountname-->
                            </div>
                            <!--end::Item-->
                            <!--begin::Item-->
                            <div class="d-flex flex-stack text-gray-800 fs-6">
                                <!--begin::Accountname-->
                                <div class="fw-semibold pe-5">Accreditation: {{ $supplier->accredition }}</div>
                                <!--end::Accountname-->
                            </div>
                            <!--end::Item-->
                        </div>
                        <!--end::Section--> 
                    </div>
                </div>
                <!--end::Content-->
                <!--begin::Separator-->
                <div class="border-end d-none d-md-block mh-450px mx-9"></div>
                <!--end::Separator-->
                <!--begin::Content-->
                <div class="text-end pt-10">
                    <!--begin::Total Amount-->
                    <div class="fs-3 fw-bold text-muted mb-3">TOTAL AMOUNT</div>
                    <div class="fs-xl-2x fs-2 fw-bolder mb-7">₱ {{ number_format($sale->total, 2) }}</div>
                    {{-- <div class="text-muted fw-semibold">Taxes included</div> --}}
                    <div class="fs-5 fw-semibold text-muted mb-1">VATable Sales</div>
                    <div class="fs-5 fw-normal mb-3">₱ {{ number_format($sale->vatable, 2) }}</div>
                    <div class="fs-5 fw-semibold text-muted mb-1">VAT Amount</div>
                    <div class="fs-5 fw-normal mb-3">₱ {{ number_format($sale->vat, 2) }}</div>
                    <div class="fs-5 fw-semibold text-muted mb-1">Non VAT</div>
                    <div class="fs-5 fw-normal mb-3">₱ {{ number_format($sale->vat_exempt, 2) }}</div>
                    <div class="fs-5 fw-semibold text-muted mb-1">Zero Rated</div>
                    <div class="fs-5 fw-normal mb-3">₱ {{ number_format($sale->zero_rated, 2) }}</div>
                    <!--end::Total Amount-->
                    <div class="border-bottom w-100 my-2 my-lg-7"></div>
                    <!--begin::Invoice To-->
                    <div class="text-gray-600 fs-6 fw-semibold mb-3">INVOICE TO.</div>
                    <div class="fs-6 text-gray-800 fw-semibold mb-8">
                        @if ($sale->customer)
                            {{ $sale->customer->name }}
                            <br>
                            {{ ($sale->customer->tin) ? $sale->customer->tin . '<br>' : '' }}
                            {{ ($sale->customer->tin) ? $sale->customer->address : '' }}
                        @else
                            Walk-In
                        @endif
                    </div>
                    <!--end::Invoice To-->
                    <!--begin::Invoice No-->
                    <div class="text-gray-600 fs-6 fw-semibold mb-3">INVOICE NO.</div>
                    <div class="fs-6 text-gray-800 fw-semibold mb-8">{{ $sale->son }}</div>
                    <!--end::Invoice No-->
                    <!--begin::Invoice Date-->
                    <div class="text-gray-600 fs-6 fw-semibold mb-3">DATE</div>
                    <div class="fs-6 text-gray-800 fw-semibold">{{ date_format($sale->created_at, "d M, Y h:i A") }}</div>
                    <!--end::Invoice Date-->
                </div>
                <!--end::Content-->
            </div>
        </div>
    </div>
@endsection