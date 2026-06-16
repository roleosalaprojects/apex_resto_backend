@extends('layout.app')
@section('header')
    - Adjustments (Returns)
@endsection
@section('title')
    Adjustments (Returns)
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.bir.annexf') }}">BIR Annex F</a></li>
    <li class="breadcrumb-item text-muted">Adjustments</li>
@endsection
@section('content')
    <div class="card">
        <div class="card-body">
            @include('admin.reports.bir.annexf._filter', ['exportKey' => 'adjustments'])
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>Return No.</th>
                        <th>SI No.</th>
                        <th>Original SI ID</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">VAT</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td>{{ $r['return_no'] }}</td>
                            <td>{{ $r['si_no'] }}</td>
                            <td>{{ $r['original_si_id'] }}</td>
                            <td>{{ $r['date'] }}</td>
                            <td class="text-end">{{ number_format($r['amount'], 2) }}</td>
                            <td class="text-end">{{ number_format($r['vat'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No adjustments in this range.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
