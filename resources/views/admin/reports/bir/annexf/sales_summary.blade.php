@extends('layout.app')
@section('header')
    - BIR Sales Summary
@endsection
@section('title')
    BIR Sales Summary
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.bir.annexf') }}">BIR Annex F</a></li>
    <li class="breadcrumb-item text-muted">Sales Summary</li>
@endsection
@section('content')
    <div class="card">
        <div class="card-body">
            @include('admin.reports.bir.annexf._filter', ['exportKey' => 'sales-summary'])
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Beginning SI</th>
                        <th>Ending SI</th>
                        <th class="text-end">Gross</th>
                        <th class="text-end">Discounts</th>
                        <th class="text-end">Returns</th>
                        <th class="text-end">Voids</th>
                        <th class="text-end">VAT Adj.</th>
                        <th class="text-end">Net</th>
                        <th class="text-end">Vatable</th>
                        <th class="text-end">VAT</th>
                        <th class="text-end">VAT-Exempt</th>
                        <th class="text-end">Zero-Rated</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td>{{ $r['day'] }}</td>
                            <td>{{ $r['beginning_si'] }}</td>
                            <td>{{ $r['ending_si'] }}</td>
                            <td class="text-end">{{ number_format($r['gross_sales'], 2) }}</td>
                            <td class="text-end">{{ number_format($r['discounts'], 2) }}</td>
                            <td class="text-end">{{ number_format($r['returns'], 2) }}</td>
                            <td class="text-end">{{ number_format($r['voids'], 2) }}</td>
                            <td class="text-end">{{ number_format($r['vat_adjustments'], 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($r['net_sales'], 2) }}</td>
                            <td class="text-end">{{ number_format($r['vatable'], 2) }}</td>
                            <td class="text-end">{{ number_format($r['vat'], 2) }}</td>
                            <td class="text-end">{{ number_format($r['vat_exempt'], 2) }}</td>
                            <td class="text-end">{{ number_format($r['zero_rated'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="text-center text-muted">No sales in this range.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
