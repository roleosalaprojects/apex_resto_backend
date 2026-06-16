@extends('layout.app')
@section('header')
    - Voided Transactions
@endsection
@section('title')
    Voided Transactions
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.bir.annexf') }}">BIR Annex F</a></li>
    <li class="breadcrumb-item text-muted">Voided</li>
@endsection
@section('content')
    <div class="card">
        <div class="card-body">
            @include('admin.reports.bir.annexf._filter', ['exportKey' => 'voided'])
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>Void No.</th>
                        <th>SI No.</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                        <th>Approved By</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td>{{ $r['void_no'] }}</td>
                            <td>{{ $r['si_no'] }}</td>
                            <td>{{ $r['date'] }}</td>
                            <td class="text-end">{{ number_format($r['amount'], 2) }}</td>
                            <td>{{ $r['approved_by'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No voided transactions in this range.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
