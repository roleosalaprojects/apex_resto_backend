@extends('layout.app')

@section('header')
    - Expense {{ $expense->reference_number }}
@endsection

@section('title')
    Expense Details
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('expenses.index') }}">Expenses</a></li>
    <li class="breadcrumb-item text-muted">{{ $expense->reference_number }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-8">
            <div class="card mb-5">
                <div class="card-header">
                    <h3 class="card-title fw-bold text-dark">
                        {{ $expense->reference_number }}
                    </h3>
                    <div class="card-toolbar">
                        @if ($expense->isVoided())
                            <span class="badge badge-light-danger fs-7 fw-bold">Voided</span>
                        @else
                            <span class="badge badge-light-success fs-7 fw-bold">Active</span>
                        @endif
                    </div>
                </div>
                <div class="card-body py-4">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="text-gray-500 fs-7">Payee</div>
                            <div class="fw-bold fs-5 text-dark">{{ $expense->payee }}</div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="text-gray-500 fs-7">Amount</div>
                            <div class="fw-bold fs-3 text-dark">₱{{ number_format((float) $expense->amount, 2) }}</div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="text-gray-500 fs-7">Expense Date</div>
                            <div class="fw-semibold text-dark">{{ \Carbon\Carbon::parse($expense->expense_date)->format('M d, Y') }}</div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="text-gray-500 fs-7">Category</div>
                            <div class="fw-semibold text-dark">{{ $expense->category?->name ?? '—' }}</div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="text-gray-500 fs-7">Branch / Store</div>
                            <div class="fw-semibold text-dark">{{ $expense->store?->name ?? '—' }}</div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="text-gray-500 fs-7">Supplier</div>
                            <div class="fw-semibold text-dark">{{ $expense->supplier?->name ?? '—' }}</div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="text-gray-500 fs-7">Receipt Number</div>
                            <div class="fw-semibold text-dark">{{ $expense->receipt_number ?? '—' }}</div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="text-gray-500 fs-7">Recorded By</div>
                            <div class="fw-semibold text-dark">{{ $expense->createdBy?->name ?? 'System' }}</div>
                        </div>
                        <div class="col-12 mb-4">
                            <div class="text-gray-500 fs-7">Description</div>
                            <div class="fw-semibold text-dark">{{ $expense->description ?? '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            @if ($expense->bank || $expense->bankTransaction)
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title fw-bold text-dark">Bank Movement</h3>
                    </div>
                    <div class="card-body py-4">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="text-gray-500 fs-7">Bank Account</div>
                                <div class="fw-semibold text-dark">
                                    {{ $expense->bank?->bank_name ?? '—' }}
                                    @if ($expense->bank?->account_number)
                                        <span class="text-gray-500 fs-7">({{ $expense->bank->account_number }})</span>
                                    @endif
                                </div>
                            </div>
                            @if ($expense->bankTransaction)
                                <div class="col-md-6 mb-4">
                                    <div class="text-gray-500 fs-7">Transaction Reference</div>
                                    <div class="fw-semibold text-dark">{{ $expense->bankTransaction->reference_number }}</div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="text-gray-500 fs-7">Balance Before</div>
                                    <div class="fw-semibold text-dark">₱{{ number_format((float) $expense->bankTransaction->balance_before, 2) }}</div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="text-gray-500 fs-7">Balance After</div>
                                    <div class="fw-semibold text-dark">₱{{ number_format((float) $expense->bankTransaction->balance_after, 2) }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="alert alert-info mb-5">
                    <i class="ki-outline ki-information fs-2x text-info me-3"></i>
                    Cashless entry — no bank movement is associated with this expense.
                </div>
            @endif

            @if ($expense->isVoided())
                <div class="card mb-5 border border-danger">
                    <div class="card-header bg-light-danger">
                        <h3 class="card-title fw-bold text-danger">Void Record</h3>
                    </div>
                    <div class="card-body py-4">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="text-gray-500 fs-7">Voided At</div>
                                <div class="fw-semibold text-dark">{{ $expense->voided_at?->format('M d, Y h:i A') ?? '—' }}</div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="text-gray-500 fs-7">Voided By</div>
                                <div class="fw-semibold text-dark">
                                    @if ($expense->voided_by)
                                        {{ \App\Models\User::find($expense->voided_by)?->name ?? '—' }}
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                            <div class="col-12 mb-4">
                                <div class="text-gray-500 fs-7">Reason</div>
                                <div class="fw-semibold text-dark">{{ $expense->void_reason ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-4">
            <div class="card mb-5">
                <div class="card-header">
                    <h3 class="card-title fw-bold text-dark">Receipt Photo</h3>
                </div>
                <div class="card-body py-4 text-center">
                    @if ($expense->receipt_photo)
                        {{-- FsLightbox: opens an in-page overlay with the
                             full-size image. Clicking the backdrop or pressing
                             ESC closes it. data-fslightbox groups items so
                             additional photos on the same page (e.g. a future
                             proof slip) can navigate as a gallery. --}}
                        <a href="{{ $expense->receipt_photo_url }}"
                           data-fslightbox="expense-receipt"
                           data-type="image"
                           style="cursor: zoom-in;">
                            <img src="{{ $expense->receipt_photo_url }}"
                                 alt="Receipt"
                                 class="img-fluid rounded"
                                 style="max-height: 400px;">
                        </a>
                        <div class="text-gray-500 fs-7 mt-3">Click the photo to view it full size.</div>
                    @else
                        <div class="text-gray-500 py-10">
                            <i class="ki-outline ki-picture fs-3x text-muted mb-3"></i>
                            <div>No receipt photo attached.</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title fw-bold text-dark">Audit</h3>
                </div>
                <div class="card-body py-4">
                    <div class="mb-3">
                        <div class="text-gray-500 fs-7">Created</div>
                        <div class="fw-semibold text-dark">{{ $expense->created_at?->format('M d, Y h:i A') ?? '—' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-gray-500 fs-7">Last Updated</div>
                        <div class="fw-semibold text-dark">{{ $expense->updated_at?->format('M d, Y h:i A') ?? '—' }}</div>
                    </div>
                    @if ($expense->approved_at)
                        <div class="mb-3">
                            <div class="text-gray-500 fs-7">Approved</div>
                            <div class="fw-semibold text-dark">{{ $expense->approved_at?->format('M d, Y h:i A') }}</div>
                            <div class="text-gray-500 fs-7">by {{ $expense->approvedBy?->name ?? '—' }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end mt-4">
        <a href="{{ route('expenses.index') }}" class="btn btn-light">
            <i class="ki-outline ki-arrow-left fs-3"></i> Back to Expenses
        </a>
    </div>
@endsection

@section('scripts')
    {{-- FsLightbox: ships with the Metronic plugin bundle but is not
         auto-loaded by the layout. The Receipt Photo card uses
         data-fslightbox so the image opens in an in-page overlay
         instead of navigating to a new tab. --}}
    <script src="{{ asset('assets/plugins/custom/fslightbox/fslightbox.bundle.js') }}"></script>
@endsection
