@extends('layout.app')
@section('header')
    - Voucher Details
@endsection
@section('title')
    Voucher Details
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('vouchers.index') }}">Vouchers</a></li>
    <li class="breadcrumb-item pe-3 text-muted">{{ $voucher->code }}</li>
@endsection
@section('actions')
    <a href="{{ route('vouchers.edit', $voucher) }}" class="btn btn-primary">
        <i class="fas fa-edit me-1"></i> Edit
    </a>
@endsection
@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm mb-5">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="ki-duotone ki-ticket text-primary fs-1 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        Voucher Information
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="text-muted fs-7">Code</label>
                            <div class="fs-4 fw-bold">
                                <span class="badge badge-light-primary fs-3 p-3">{{ $voucher->code }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted fs-7">Name</label>
                            <div class="fs-5 fw-semibold">{{ $voucher->name }}</div>
                        </div>
                    </div>

                    <div class="row mb-5">
                        <div class="col-md-4">
                            <label class="text-muted fs-7">Discount Amount</label>
                            <div class="fs-4 fw-bold text-success">₱{{ number_format($voucher->amount, 2) }}</div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted fs-7">Minimum Cart</label>
                            <div class="fs-5">₱{{ number_format($voucher->minimum_amount, 2) }}</div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted fs-7">Store</label>
                            <div class="fs-5">{{ $voucher->store?->name ?? 'All Stores' }}</div>
                        </div>
                    </div>

                    <div class="row mb-5">
                        <div class="col-md-4">
                            <label class="text-muted fs-7">Expiration</label>
                            <div class="fs-5">
                                {{ $voucher->expires_at->format('M d, Y h:i A') }}
                                @if($voucher->isExpired())
                                    <span class="badge badge-danger ms-2">Expired</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted fs-7">Status</label>
                            <div>
                                @if($voucher->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-secondary">Inactive</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted fs-7">Created</label>
                            <div class="fs-5">{{ $voucher->created_at->format('M d, Y') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            @if($voucher->usages->count() > 0)
            <div class="card shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="ki-duotone ki-chart-simple fs-1 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                        Usage History
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-100">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>Date</th>
                                    <th>Sale ID</th>
                                    <th>Store</th>
                                    <th>User</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($voucher->usages as $usage)
                                <tr>
                                    <td>{{ $usage->created_at->format('M d, Y h:i A') }}</td>
                                    <td>#{{ $usage->sale_id }}</td>
                                    <td>{{ $usage->store?->name ?? '-' }}</td>
                                    <td>{{ $usage->user?->name ?? '-' }}</td>
                                    <td class="text-end">₱{{ number_format($usage->amount_applied, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Usage Statistics</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="text-muted">Total Uses</span>
                            <span class="fs-4 fw-bold">{{ $voucher->used_count }} / {{ $voucher->max_uses }}</span>
                        </div>
                        <div class="progress h-20px mb-3">
                            @php
                                $percentage = $voucher->max_uses > 0 ? ($voucher->used_count / $voucher->max_uses) * 100 : 0;
                            @endphp
                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $percentage }}%"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="text-muted">Remaining</span>
                            <span class="fs-4 fw-bold text-success">{{ $voucher->remaining_uses }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Total Discount Given</span>
                            <span class="fs-4 fw-bold text-primary">₱{{ number_format($voucher->usages->sum('amount_applied'), 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
