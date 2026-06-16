@extends('layout.app')
@section('header')
    - Discount Sales Book
@endsection
@section('title')
    Discount Sales Book
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.bir.annexf') }}">BIR Annex F</a></li>
    <li class="breadcrumb-item text-muted">Discount Sales Book</li>
@endsection
@section('content')
    <div class="card">
        <div class="card-body">
            @include('admin.reports.bir.annexf._filter', ['exportKey' => 'discount-book'])
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>SI No.</th>
                        <th>Customer</th>
                        <th>ID No.</th>
                        <th>TIN</th>
                        <th class="text-end">Gross</th>
                        <th class="text-end">Discount</th>
                        <th class="text-end">Net</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td>{{ $r['date'] }}</td>
                            <td>{{ $r['si_no'] }}</td>
                            <td>{{ $r['customer'] }}</td>
                            <td>{{ $r['id_no'] }}</td>
                            <td>{{ $r['tin'] }}</td>
                            <td class="text-end">{{ number_format($r['gross'], 2) }}</td>
                            <td class="text-end">{{ number_format($r['discount'], 2) }}</td>
                            <td class="text-end">{{ number_format($r['net'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">No discount sales in this range.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
