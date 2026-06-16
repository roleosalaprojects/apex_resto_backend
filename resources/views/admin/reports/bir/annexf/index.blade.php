@extends('layout.app')
@section('header')
    - BIR Annex F Reports
@endsection
@section('title')
    BIR Annex F Reports (RMO 24-2023)
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item text-muted">BIR Annex F</li>
@endsection
@section('content')
    <div class="row g-5">
        @php
            $cards = [
                ['BIR Sales Summary', 'Per-day beginning/ending SI, gross, less discounts/returns/voids, net & VAT breakdown.', route('reports.bir.annexf.sales-summary')],
                ['Voided Transactions', 'All voided invoices with their void document numbers.', route('reports.bir.annexf.voided')],
                ['Discount Sales Book', 'SC / PWD / Solo Parent / NAAC discount ledger.', route('reports.bir.annexf.discount-book')],
                ['Adjustments (Returns)', 'Returns/refunds with return document numbers.', route('reports.bir.annexf.adjustments')],
                ['Daily Sales by VAT Class', 'Vatable, VAT, VAT-exempt and zero-rated per day.', route('reports.bir.annexf.vat-class')],
            ];
        @endphp
        @foreach($cards as [$title, $desc, $url])
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h4>{{ $title }}</h4>
                        <p class="text-muted">{{ $desc }}</p>
                        <a href="{{ $url }}" class="btn btn-primary btn-sm">Open</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
