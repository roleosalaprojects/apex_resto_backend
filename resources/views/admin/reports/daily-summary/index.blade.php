@extends('layout.app')
@section('header')
    - Daily Summary
@endsection
@section('title')
    Daily Summary
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.sales_summary') }}">Reports</a></li>
    <li class="breadcrumb-item text-muted">Daily Summary</li>
@endsection
@section('content')
    <div class="card mb-5">
        <div class="card-body py-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fs-7 fw-semibold">Date</label>
                    <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-primary w-100">View</button>
                </div>
                <div class="col-md-7 text-end">
                    <span class="text-muted fs-7">
                        Operational view: includes POS sales and admin-recorded cashless sales.
                    </span>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-5 mb-5">
        <div class="col-md-3">
            <div class="card card-flush h-100">
                <div class="card-body">
                    <div class="text-muted fs-7 fw-semibold mb-2">Sales</div>
                    <div class="fs-1 fw-bold text-success">₱{{ number_format($summary['sales'], 2) }}</div>
                    @if(! is_null($summary['comparison']['change_pct']))
                        <div class="text-muted fs-8 mt-1">
                            {{ $summary['comparison']['change_pct'] >= 0 ? '↑' : '↓' }}
                            {{ abs($summary['comparison']['change_pct']) }}% vs yesterday
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush h-100">
                <div class="card-body">
                    <div class="text-muted fs-7 fw-semibold mb-2">Profit</div>
                    <div class="fs-1 fw-bold text-primary">₱{{ number_format($summary['profit'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush h-100">
                <div class="card-body">
                    <div class="text-muted fs-7 fw-semibold mb-2">Refunds</div>
                    <div class="fs-1 fw-bold text-warning">₱{{ number_format($summary['refunds'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush h-100">
                <div class="card-body">
                    <div class="text-muted fs-7 fw-semibold mb-2">Transactions</div>
                    <div class="fs-1 fw-bold">{{ number_format($summary['transactions']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5">
        <div class="col-md-6">
            <div class="card card-flush h-100">
                <div class="card-header">
                    <h3 class="card-title fw-bold">Cashless Sales (Web Admin)</h3>
                </div>
                <div class="card-body pt-0">
                    @if(empty($cashless))
                        <div class="text-muted fs-7">No cashless sales recorded for this date.</div>
                    @else
                        <table class="table table-row-bordered table-row-gray-200 align-middle gy-3">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>Method</th>
                                    <th class="text-end">Count</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cashless as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td class="text-end">{{ $row['count'] }}</td>
                                        <td class="text-end fw-bold">₱{{ number_format($row['total'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="text-muted fs-7 mt-3">
                            These rows are part of the Sales total above, broken out by recording method.
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-flush h-100 {{ $pending['count'] > 0 ? 'border border-warning' : '' }}">
                <div class="card-header">
                    <h3 class="card-title fw-bold">Pending Cheques</h3>
                    <div class="card-toolbar">
                        <a href="{{ route('pending-cheques.index') }}" class="btn btn-sm btn-light-warning">Manage</a>
                    </div>
                </div>
                <div class="card-body pt-0">
                    @if($pending['count'] === 0)
                        <div class="text-muted fs-7">No cheques are awaiting clearing right now.</div>
                    @else
                        <div class="d-flex align-items-center gap-5 mb-3">
                            <div>
                                <div class="text-muted fs-7 fw-semibold">Count</div>
                                <div class="fs-2 fw-bold">{{ $pending['count'] }}</div>
                            </div>
                            <div>
                                <div class="text-muted fs-7 fw-semibold">Total</div>
                                <div class="fs-2 fw-bold">₱{{ number_format($pending['total'], 2) }}</div>
                            </div>
                            @if(! is_null($pending['oldest_days']))
                                <div>
                                    <div class="text-muted fs-7 fw-semibold">Oldest</div>
                                    <div class="fs-2 fw-bold {{ $pending['oldest_days'] > 30 ? 'text-danger' : 'text-warning' }}">
                                        {{ $pending['oldest_days'] }}d
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="text-muted fs-7">
                            These cheques have not cleared. The money is not yet in the bank.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
