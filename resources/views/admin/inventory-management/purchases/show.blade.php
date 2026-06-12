@extends('layout.app')
@section('title')
    PO #: {{ $purchase->po }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('purchases.index')}}">Purchase Orders</a></li>
    <li class="breadcrumb-item text-muted">PO #: {{$purchase->po}}</li>
@endsection
@section('actions')
    {{-- Approve/Reject buttons for pending POs --}}
    @if ($purchase->approval_status == 1 && $access->prchs_approve && $purchase->created_by !== auth()->user()->id)
        <form action="{{ route('purchase.approve', $purchase->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Are you sure you want to approve this purchase order?')">
                <i class="fas fa-check me-1"></i>Approve
            </button>
        </form>
        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
            <i class="fas fa-times me-1"></i>Reject
        </button>
    @endif

    {{-- Self-approval warning --}}
    @if ($purchase->approval_status == 1 && $access->prchs_approve && $purchase->created_by === auth()->user()->id)
        <span class="badge bg-secondary me-2" data-bs-toggle="tooltip" title="You cannot approve your own purchase order">Cannot Self-Approve</span>
    @endif

    {{-- Record Payment button - only show if approved and not fully paid --}}
    @if ($purchase->approval_status == 2 && $purchase->payment_status != 2 && $access->prchs_update)
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">
            <i class="fas fa-money-bill-wave me-1"></i>Record Payment
        </button>
    @endif

    @if ($purchase->status == 0)
        {{-- Only show receive if approved --}}
        @if ($purchase->items - $purchase->received > 0 && $purchase->approval_status == 2)
            <a href="{{route('purchase.receive', $purchase->id)}}" class="btn btn-danger btn-sm">Receive</a>
        @endif
    @else
        {{-- Only show receive if approved --}}
        @if ($purchase->approval_status == 2)
            <a href="{{route('purchase.receive', $purchase->id)}}" class="btn btn-sm btn-warning">Receive</a>
        @endif
        {{-- Only show edit if not approved --}}
        @if ($purchase->approval_status != 2)
            <a href="{{route('purchases.edit', $purchase->id)}}" class="btn btn-sm btn-info">Edit</a>
        @endif
    @endif
    @if ($access->prchs_read)
        <a href="{{route('purchase.print', $purchase->id)}}" class="btn btn-active-dark btn-bg-light btn-sm" rel="noopener" target="_blank">Print</a>
    @endif
@endsection
@section('content')
    <div class="col">
        <div class="card card-flush mb-7">
            <div class="card-header">
                <div class="card-title fs-1">Purchase Details</div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-8">
                        <h3>
                            PO #: {{$purchase->po}}
                            {{-- Approval Status Badge --}}
                            @if ($purchase->approval_status == 1)
                                <span class="badge bg-warning ms-2">Pending Approval</span>
                            @elseif ($purchase->approval_status == 2)
                                <span class="badge bg-success ms-2">Approved</span>
                            @elseif ($purchase->approval_status == 3)
                                <span class="badge bg-danger ms-2">Rejected</span>
                            @endif
                        </h3>
                    </div>
                    <div class="col-4">
                        <div class="progress progress-xs">
                            <div class="progress-bar @if($purchase->items - $purchase->received != 0) bg-info @else bg-success @endif progress-bar-striped" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: {{($purchase->received) ? ($purchase->received / $purchase->items) * 100 : 0}}%"></div>
                        </div>
                        <small>{{$purchase->received}} of {{$purchase->items}} received</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col fs-4">
                        @if ($purchase->status == 1)
                            <span class="text-info">Pending</span>
                        @else
                            @if ($purchase->items - $purchase->received > 0)
                                <span class="text-info">Partially Received</span>
                            @else
                                <span class="text-success">Received</span>
                            @endif
                        @endif
                    </div>
                </div>

                <br>

                <div class="row">
                    <div class="col-6">
                        <span class="fw-bold fs-4">
                            Purchase Date:
                            <span class="text-success">
{{\Carbon\Carbon::parse($purchase->purchased)->format('M d, Y')}}
                            </span>
                        </span>
                    </div>
                    <div class="col-6">
                        <div class="row">
                            <div class="col">
                                <span class="fw-bold fs-4">
                                    Due Date:
                                    <span class="text-danger">
                                        {{ \Carbon\Carbon::parse($purchase->purchased)->addDays($purchase->expected-1)->format('M d, Y') }}
                                    </span>
                                </span>
                            </div>
                            <div class="col">
                                <span class="fw-bold fs-4">
                                    Terms:
                                    <span class="">
                                        {{ (int)$purchase->expected }} &nbspDays
                                    </span>

                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <br>

                <div class="row fs-4">
                    <div class="col-6">
                        <strong>Supplier:</strong>&nbsp{{$purchase->supplier->name}}
                    </div>
                    <div class="col-6">
                        <strong>Store:</strong>&nbsp{{$purchase->store->name}}
                    </div>
                </div>

                <br>

                <div class="row fs-4">
                    <div class="col-6">
                        <strong>Created by: <span class="">{{$purchase->creator->name}}</strong></span>
                    </div>
                    <div class="col-6">
                        <strong>Received By: <span class="text-info">{{($purchase->received_by) ? $purchase->receiver->name : "N/A"}}</span></strong>
                    </div>
                </div>

                <br>

                <div class="row">
                    <table class="table table-hover">
                        <thead>
                            <tr class="fw-semibold fs-6 text-gray-800 border-bottom border-gray-200">
                                <th>Item</th>
                                <th>Unit</th>
                                <th>Ordered</th>
                                <th>Received</th>
                                <th>Price</th>
                                <th>Sub Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $totalItems = 0;
                            @endphp
                            @foreach ($purchase->lines as $line)
                                @php
                                    $unit_qty = 1;
                                    $sub_total = 0;
                                @endphp
                                <tr>
                                    <td>
                                        {{$line->item->name}}
                                    </td>
                                    <td>
                                        @if ($line->unit_id == '' || $line->unit_id == null)
                                            {{ $line->item->type == 0 ? 'PCS' : 'KGS' }}
                                        @else
                                            @foreach ($line->item->itemUnits as $item_unit)
                                                @if ($item_unit->unit_id == $line->unit_id)
                                                    {{ $item_unit->unit->name }} ({{ $item_unit->qty }})
                                                    @php
                                                        $unit_qty = $item_unit->qty
                                                    @endphp
                                                @endif
                                            @endforeach
                                        @endif
                                    </td>
                                    @php
                                        $sub_total = $line->qty * $line->cost;
                                        $totalItems += $sub_total;
                                    @endphp
                                    <td>{{$line->qty}}</td>
                                    <td>{{$line->received}}</td>
                                    <td>₱ {{ number_format($line->cost, 2) }}</td>
                                    <td>₱ {{ number_format($sub_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4"></td>
                                <td colspan="1">
                                    <strong class="fs-4">Total</strong>
                                </td>
                                <td>
                                    <strong class="fs-4">₱ {{ number_format($totalItems, 2) }}</strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    </div>
    @if (count($purchase->adds) > 0)
        <div class="row">
            <div class="col-md-6"></div>
            <div class="col-md-6">
                <div class="card card-flush">
                    <div class="card-header">
                        <div class="card-title">Additionals</div>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr class="fw-semibold fs-6 text-gray-800 border-bottom border-gray-200">
                                    <th>Description</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $addTotal = 0;
                                @endphp
                                @foreach ($purchase->adds as $add)
                                    <tr>
                                        <td>
                                            {{$add->description}}
                                        </td>
                                        <td>
                                            ₱ {{number_format($add->amount, 2)}}
                                        </td>
                                    </tr>
                                    @php
                                        $addTotal += $add->amount;
                                    @endphp
                                @endforeach
                                <tr  class="fw-semibold fs-6 text-gray-800 border-bottom border-gray-200">
                                    <td>Total</td>
                                    <td>₱ {{ number_format($addTotal, 2) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td><h3>Grand Total:</h3></td>
                                    <td><h2>{{number_format($purchase->total, 2)}}</h2></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Rejection Comment Display --}}
    @if ($purchase->approval_status == 3 && $purchase->latestApproval && $purchase->latestApproval->rejection_comment)
        <div class="row mt-5">
            <div class="col-12">
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="fas fa-exclamation-circle fs-2 me-3"></i>
                    <div>
                        <h5 class="mb-1">Rejection Reason</h5>
                        <p class="mb-0">{{ $purchase->latestApproval->rejection_comment }}</p>
                        @if ($purchase->latestApproval->approver)
                            <small class="text-muted">Rejected by: {{ $purchase->latestApproval->approver->name }} on {{ $purchase->latestApproval->approved_at ? \Carbon\Carbon::parse($purchase->latestApproval->approved_at)->format('M d, Y h:i A') : '' }}</small>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Payment Status Section - Only show for approved POs --}}
    @if ($purchase->approval_status == 2)
        <div class="row mt-5">
            <div class="col-12">
                <div class="card card-flush">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-credit-card me-2"></i>Payment Status
                        </h3>
                        <div class="card-toolbar">
                            @if ($purchase->payment_status == 0)
                                <span class="badge bg-danger">Unpaid</span>
                            @elseif ($purchase->payment_status == 1)
                                <span class="badge bg-warning">Partial</span>
                            @else
                                <span class="badge bg-success">Paid</span>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-5">
                            <div class="col-md-8">
                                @php
                                    $paymentPercent = $purchase->total > 0 ? (($purchase->amount_paid ?? 0) / $purchase->total) * 100 : 0;
                                    $paymentPercent = min(100, $paymentPercent);
                                @endphp
                                <div class="progress progress-xs mb-2" style="height: 10px;">
                                    <div class="progress-bar @if($paymentPercent >= 100) bg-success @elseif($paymentPercent > 0) bg-warning @else bg-secondary @endif progress-bar-striped"
                                         role="progressbar"
                                         id="paymentProgressBar"
                                         style="width: {{ $paymentPercent }}%"
                                         aria-valuenow="{{ $paymentPercent }}"
                                         aria-valuemin="0"
                                         aria-valuemax="100"></div>
                                </div>
                                <small id="paymentProgressText">{{ number_format($paymentPercent, 0) }}% paid</small>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex flex-column align-items-md-end">
                                    <div class="mb-1">
                                        <span class="text-muted">Total:</span>
                                        <span class="fw-bold fs-5">{{ number_format($purchase->total, 2) }}</span>
                                    </div>
                                    <div class="mb-1">
                                        <span class="text-muted">Paid:</span>
                                        <span class="fw-bold fs-5 text-success" id="amountPaidDisplay">{{ number_format($purchase->amount_paid ?? 0, 2) }}</span>
                                    </div>
                                    <div>
                                        <span class="text-muted">Remaining:</span>
                                        <span class="fw-bold fs-5 @if($purchase->remaining_balance > 0) text-danger @else text-success @endif" id="remainingBalanceDisplay">{{ number_format($purchase->remaining_balance, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Payment History --}}
                        @if ($purchase->payments->count() > 0)
                            <div class="separator my-5"></div>
                            <h5 class="mb-4"><i class="fas fa-history me-2"></i>Payment History</h5>
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4" id="paymentHistoryTable">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th>Reference</th>
                                            <th>Date</th>
                                            <th>Method</th>
                                            <th>Bank</th>
                                            <th>Check #</th>
                                            <th class="text-end">Amount</th>
                                            <th>By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($purchase->payments as $payment)
                                            <tr>
                                                <td>{{ $payment->reference_number }}</td>
                                                <td>{{ $payment->payment_date?->format('M d, Y') }}</td>
                                                <td>
                                                    @if ($payment->payment_method == 1)
                                                        <span class="badge bg-success">Cash</span>
                                                    @elseif ($payment->payment_method == 2)
                                                        <span class="badge bg-info">Check</span>
                                                    @elseif ($payment->payment_method == 3)
                                                        <span class="badge bg-primary">Bank Transfer</span>
                                                    @else
                                                        <span class="badge bg-secondary">E-Wallet</span>
                                                    @endif
                                                </td>
                                                <td>{{ $payment->bank?->bank_name ?? 'N/A' }}</td>
                                                <td>{{ $payment->check_number ?? '-' }}</td>
                                                <td class="text-end fw-bold">{{ number_format($payment->amount, 2) }}</td>
                                                <td>{{ $payment->createdBy?->name ?? 'N/A' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-receipt fs-1 mb-3 d-block"></i>
                                <p>No payments recorded yet.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Rejection Modal --}}
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('purchase.reject', $purchase->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="rejectModalLabel">Reject Purchase Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to reject PO #{{ $purchase->po }}?</p>
                        <div class="mb-3">
                            <label for="rejection_comment" class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('rejection_comment') is-invalid @enderror" id="rejection_comment" name="rejection_comment" rows="4" placeholder="Please provide a reason for rejection (minimum 10 characters)" required minlength="10">{{ old('rejection_comment') }}</textarea>
                            @error('rejection_comment')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Payment Modal --}}
    @if ($purchase->approval_status == 2 && $purchase->payment_status != 2 && $access->prchs_update)
        <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form id="paymentForm">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="paymentModalLabel">
                                <i class="fas fa-money-bill-wave text-primary me-2"></i>Record Payment - PO #{{ $purchase->po }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info d-flex align-items-center mb-5">
                                <i class="fas fa-info-circle me-3 fs-3"></i>
                                <div>
                                    <strong>Remaining Balance:</strong>
                                    <span class="fs-4 fw-bold ms-2" id="modalRemainingBalance">{{ number_format($purchase->remaining_balance, 2) }}</span>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-5 fv-row">
                                    <label for="bank_id" class="form-label required">Bank Account</label>
                                    <select class="form-select" id="bank_id" name="bank_id">
                                        <option value="">Select Bank Account...</option>
                                        @foreach ($banks as $bank)
                                            <option value="{{ $bank->id }}" data-balance="{{ $bank->balance }}">
                                                {{ $bank->bank_name }} - {{ $bank->account_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted" id="bankBalanceInfo"></small>
                                </div>
                                <div class="col-md-6 mb-5 fv-row">
                                    <label for="payment_method" class="form-label required">Payment Method</label>
                                    <select class="form-select" id="payment_method" name="payment_method">
                                        <option value="">Select Payment Method...</option>
                                        @foreach ($paymentMethods as $method)
                                            <option value="{{ $method['value'] }}">{{ $method['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-5 fv-row">
                                    <label for="amount" class="form-label required">Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">PHP</span>
                                        <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" placeholder="0.00">
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light-primary mt-2" id="payFullBtn">
                                        Pay Full {{ number_format($purchase->remaining_balance, 2) }}
                                    </button>
                                </div>
                                <div class="col-md-6 mb-5 fv-row">
                                    <label for="payment_date" class="form-label required">Payment Date</label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}">
                                </div>
                            </div>

                            <div class="row" id="checkNumberRow" style="display: none;">
                                <div class="col-md-6 mb-5 fv-row">
                                    <label for="check_number" class="form-label required">Check Number</label>
                                    <input type="text" class="form-control" id="check_number" name="check_number" placeholder="Enter check number">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 mb-5 fv-row">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Optional payment notes..."></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" id="paymentSubmitBtn" class="btn btn-primary">
                                <span class="indicator-label">Record Payment</span>
                                <span class="indicator-progress">Please wait...
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
    
    @if ($purchase->approval_status == 2 && $purchase->payment_status != 2 && $access->prchs_update)
    <script>
        $(document).ready(function() {
            // Track remaining balance
            let remainingBalance = {{ $purchase->remaining_balance }};
            let selectedBankBalance = 0;

            // Show/hide check number field based on payment method
            $('#payment_method').on('change', function() {
                const selectedMethod = $(this).val();
                if (selectedMethod == '2') { // Check payment
                    $('#checkNumberRow').show();
                } else {
                    $('#checkNumberRow').hide();
                    $('#check_number').val('');
                }
            });

            // Show bank balance when bank is selected
            $('#bank_id').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const balance = parseFloat(selectedOption.data('balance')) || 0;
                selectedBankBalance = balance;
                if (balance > 0) {
                    $('#bankBalanceInfo').html('<i class="fas fa-wallet me-1"></i>Available: <strong>' + balance.toLocaleString('en-US', {minimumFractionDigits: 2}) + '</strong>');
                } else {
                    $('#bankBalanceInfo').text('');
                }
            });

            // Pay full button
            $('#payFullBtn').on('click', function() {
                $('#amount').val(remainingBalance.toFixed(2));
            });

            // =====================
            // PAYMENT FORM VALIDATION
            // =====================
            const paymentForm = document.querySelector('#paymentForm');
            const paymentSubmitBtn = document.querySelector('#paymentSubmitBtn');

            const paymentValidator = validateForm(paymentForm, {
                bank_id: {
                    validators: {
                        notEmpty: {
                            message: 'Please select a bank account'
                        }
                    }
                },
                payment_method: {
                    validators: {
                        notEmpty: {
                            message: 'Please select a payment method'
                        }
                    }
                },
                amount: {
                    validators: {
                        notEmpty: {
                            message: 'Amount is required'
                        },
                        numeric: {
                            message: 'Please enter a valid amount',
                            thousandsSeparator: '',
                            decimalSeparator: '.'
                        },
                        greaterThan: {
                            min: 0.01,
                            message: 'Amount must be greater than 0'
                        },
                        callback: {
                            message: 'Amount exceeds remaining balance of ' + remainingBalance.toLocaleString('en-US', {minimumFractionDigits: 2}),
                            callback: function(input) {
                                return parseFloat(input.value) <= remainingBalance;
                            }
                        }
                    }
                },
                payment_date: {
                    validators: {
                        notEmpty: {
                            message: 'Payment date is required'
                        },
                        date: {
                            format: 'YYYY-MM-DD',
                            message: 'Please enter a valid date'
                        }
                    }
                },
                check_number: {
                    validators: {
                        callback: {
                            message: 'Check number is required for check payments',
                            callback: function(input) {
                                const paymentMethod = $('#payment_method').val();
                                if (paymentMethod == '2') {
                                    return input.value.trim().length > 0;
                                }
                                return true;
                            }
                        },
                        stringLength: {
                            max: 50,
                            message: 'Check number must be less than 50 characters'
                        }
                    }
                },
                notes: {
                    validators: {
                        stringLength: {
                            max: 500,
                            message: 'Notes must be less than 500 characters'
                        }
                    }
                }
            });

            // Revalidate check_number when payment_method changes
            $('#payment_method').on('change', function() {
                paymentValidator.revalidateField('check_number');
            });

            paymentSubmitBtn.addEventListener('click', function(e) {
                e.preventDefault();

                // Additional bank balance check
                const amount = parseFloat($('#amount').val()) || 0;
                if (selectedBankBalance > 0 && amount > selectedBankBalance) {
                    errorSwal('Error', 'Insufficient bank balance. Available: ' + selectedBankBalance.toLocaleString('en-US', {minimumFractionDigits: 2}));
                    return;
                }

                paymentValidator.validate().then(function(status) {
                    if (status === 'Valid') {
                        disableSubmitFormButton(paymentSubmitBtn);
                        submitPayment();
                    }
                });
            });

            function submitPayment() {
                $.ajax({
                    url: '{{ route('purchase.record-payment', $purchase->id) }}',
                    method: 'POST',
                    data: $('#paymentForm').serialize(),
                    success: function(response) {
                        if (response.success) {
                            $('#paymentModal').modal('hide');
                            paymentForm.reset();
                            $('#payment_date').val('{{ date('Y-m-d') }}');
                            paymentValidator.resetForm();
                            $('#checkNumberRow').hide();
                            $('#bankBalanceInfo').text('');

                            // Update page displays
                            remainingBalance = response.purchase.remaining_balance;

                            // Update progress bar
                            const total = {{ $purchase->total }};
                            const amountPaid = response.purchase.amount_paid;
                            const percent = (amountPaid / total) * 100;

                            $('#paymentProgressBar')
                                .css('width', percent + '%')
                                .attr('aria-valuenow', percent);

                            if (percent >= 100) {
                                $('#paymentProgressBar')
                                    .removeClass('bg-warning bg-secondary')
                                    .addClass('bg-success');
                            } else if (percent > 0) {
                                $('#paymentProgressBar')
                                    .removeClass('bg-secondary bg-success')
                                    .addClass('bg-warning');
                            }

                            $('#paymentProgressText').text(percent.toFixed(0) + '% paid');
                            $('#amountPaidDisplay').text(amountPaid.toLocaleString('en-US', {minimumFractionDigits: 2}));
                            $('#remainingBalanceDisplay').text(remainingBalance.toLocaleString('en-US', {minimumFractionDigits: 2}));
                            $('#modalRemainingBalance').text(remainingBalance.toLocaleString('en-US', {minimumFractionDigits: 2}));

                            // Update remaining balance class
                            if (remainingBalance <= 0) {
                                $('#remainingBalanceDisplay').removeClass('text-danger').addClass('text-success');
                            }

                            // Update pay full button
                            $('#payFullBtn').text('Pay Full ' + remainingBalance.toLocaleString('en-US', {minimumFractionDigits: 2}));

                            // If fully paid, hide the payment button and reload to update payment history
                            if (response.purchase.payment_status == 2) {
                                successSwal('Success', response.message).then(function() {
                                    location.reload();
                                });
                            } else {
                                successSwal('Success', response.message).then(function() {
                                    location.reload();
                                });
                            }
                        }
                        enableSubmitFormButton(paymentSubmitBtn);
                    },
                    error: function(xhr) {
                        enableSubmitFormButton(paymentSubmitBtn);
                        var errors = xhr.responseJSON?.errors;
                        if (errors) {
                            var errorMsg = Object.values(errors).flat().join('\n');
                            errorSwal('Validation Error', errorMsg);
                        } else {
                            errorSwal('Error', xhr.responseJSON?.message || 'An error occurred');
                        }
                    }
                });
            }

            // Reset form validation when modal is hidden
            $('#paymentModal').on('hidden.bs.modal', function() {
                paymentForm.reset();
                paymentValidator.resetForm();
                $('#payment_date').val('{{ date('Y-m-d') }}');
                $('#checkNumberRow').hide();
                $('#bankBalanceInfo').text('');
            });
        });
    </script>
    @endif
@endsection
