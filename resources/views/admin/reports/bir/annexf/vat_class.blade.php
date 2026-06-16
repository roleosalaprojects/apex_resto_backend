@extends('layout.app')
@section('header')
    - Daily Sales by VAT Class
@endsection
@section('title')
    Daily Sales by VAT Class
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.bir.annexf') }}">BIR Annex F</a></li>
    <li class="breadcrumb-item text-muted">VAT Class</li>
@endsection
@section('content')
    <div class="card">
        <div class="card-body">
            @include('admin.reports.bir.annexf._filter', ['exportKey' => 'vat-class'])
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>Date</th>
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
                            <td class="text-end">{{ number_format($r['vatable'], 2) }}</td>
                            <td class="text-end">{{ number_format($r['vat'], 2) }}</td>
                            <td class="text-end">{{ number_format($r['vat_exempt'], 2) }}</td>
                            <td class="text-end">{{ number_format($r['zero_rated'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No sales in this range.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
