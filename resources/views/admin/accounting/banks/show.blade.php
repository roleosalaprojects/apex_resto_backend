@extends('layout.app')
@section('header')
    - {{ $bank->bank_name }}
@endsection
@section('title')
    <div class="fs-4">{{ $bank->account_name}}</div><div class="text-sm fs-7">({{$bank->account_number . ' - ' . $bank->bank_name}})</div>
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item  text-muted">
        <a href="{{ route('banks.index') }}" class="">Banking</a></li>
    <li class="breadcrumb-item  text-muted">{{ $bank->bank_name }}</li>
@endsection
@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between gap-4 mb-10">
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#depositModal">
                <i class="fas fa-plus me-1"></i>Deposit
            </button>
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#withdrawalModal">
                <i class="fas fa-minus me-1"></i>Withdrawal
            </button>
            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#transferModal">
                <i class="fas fa-exchange-alt me-1"></i>Transfer
            </button>
        </div>
        <div class="card card-flush flex-shrink-0">
            <div class="card-body py-3 px-5">
                <div class="fw-bold text-center text-md-end">
                    <span class="d-block d-md-inline fs-6 fs-md-4 mb-1 mb-md-0 me-md-4">Current Balance:</span>
                    <span class="text-{{ $bank->balance <= 0 ? 'danger' : 'primary' }} fw-bolder fs-3" id="currentBalance">{{ number_format($bank->balance, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-history me-2"></i>Transaction History
            </h3>
            <div class="card-toolbar d-flex gap-3">
                <div>
                    <input type="text" id="tableSearch" class="form-control form-control-sm" placeholder="Search transactions...">
                </div>
                <x-data-table.actions></x-data-table.actions>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4" id="transactionsTable">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Payee</th>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Balance</th>
                            <th>Proof</th>
                            <th>By</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <!-- Deposit Modal -->
    <div class="modal fade" id="depositModal" tabindex="-1" aria-labelledby="depositModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="depositForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="depositModalLabel">
                            <i class="fas fa-plus text-success me-2"></i>Record Deposit
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-5 fv-row">
                            <label for="deposit_amount" class="form-label required">Amount</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="deposit_amount" name="amount">
                        </div>
                        <div class="mb-5 fv-row">
                            <label for="deposit_payee" class="form-label">Depositor/Source</label>
                            <input type="text" class="form-control" id="deposit_payee" name="payee" placeholder="e.g., Cash Sales, Customer Payment">
                        </div>
                        <div class="mb-5 fv-row">
                            <label for="deposit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="deposit_description" name="description" rows="2"></textarea>
                        </div>
                        <div class="mb-5 fv-row">
                            <label for="deposit_date" class="form-label required">Transaction Date</label>
                            <input type="date" class="form-control" id="deposit_date" name="transaction_date" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="mb-5 fv-row">
                            <label for="deposit_proof" class="form-label">Deposit Slip / Proof Photo</label>
                            <input type="file" class="form-control" id="deposit_proof" accept="image/*">
                            <div class="form-text">Optional. Image only, max 5 MB.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="depositSubmitBtn" class="btn btn-success">
                            <span class="indicator-label">Record Deposit</span>
                            <span class="indicator-progress">Please wait...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Withdrawal Modal -->
    <div class="modal fade" id="withdrawalModal" tabindex="-1" aria-labelledby="withdrawalModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="withdrawalForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="withdrawalModalLabel">
                            <i class="fas fa-minus text-danger me-2"></i>Record Withdrawal
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info d-flex align-items-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Available Balance: <strong id="withdrawalAvailableBalance">{{ number_format($bank->balance, 2) }}</strong></small>
                        </div>
                        <div class="mb-5 fv-row">
                            <label for="withdrawal_amount" class="form-label required">Amount</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="withdrawal_amount" name="amount" data-max-balance="{{ $bank->balance }}">
                        </div>
                        <div class="mb-5 fv-row">
                            <label for="withdrawal_payee" class="form-label">Payee/Recipient</label>
                            <input type="text" class="form-control" id="withdrawal_payee" name="payee" placeholder="e.g., Supplier Name, Employee">
                        </div>
                        <div class="mb-5 fv-row">
                            <label for="withdrawal_description" class="form-label">Description</label>
                            <textarea class="form-control" id="withdrawal_description" name="description" rows="2"></textarea>
                        </div>
                        <div class="mb-5 fv-row">
                            <label for="withdrawal_date" class="form-label required">Transaction Date</label>
                            <input type="date" class="form-control" id="withdrawal_date" name="transaction_date" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="mb-5 fv-row">
                            <label for="withdrawal_proof" class="form-label">Withdrawal Slip / Proof Photo</label>
                            <input type="file" class="form-control" id="withdrawal_proof" accept="image/*">
                            <div class="form-text">Optional. Image only, max 5 MB.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="withdrawalSubmitBtn" class="btn btn-danger">
                            <span class="indicator-label">Record Withdrawal</span>
                            <span class="indicator-progress">Please wait...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Transfer Modal -->
    <div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="transferForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="transferModalLabel">
                            <i class="fas fa-exchange-alt text-info me-2"></i>Transfer Funds
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info d-flex align-items-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Available Balance: <strong id="transferAvailableBalance">{{ number_format($bank->balance, 2) }}</strong></small>
                        </div>
                        <div class="mb-5 fv-row">
                            <label for="transfer_to_bank_id" class="form-label required">Transfer To</label>
                            <select class="form-select" id="transfer_to_bank_id" name="transfer_to_bank_id">
                                <option value="">Select destination account...</option>
                                @foreach($otherBanks as $otherBank)
                                    <option value="{{ $otherBank->id }}">{{ $otherBank->account_name }} - {{ $otherBank->bank_name }} ({{ $otherBank->account_number }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-5 fv-row">
                            <label for="transfer_amount" class="form-label required">Amount</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="transfer_amount" name="amount" data-max-balance="{{ $bank->balance }}">
                        </div>
                        <div class="mb-5 fv-row">
                            <label for="transfer_description" class="form-label">Description</label>
                            <textarea class="form-control" id="transfer_description" name="description" rows="2" placeholder="Reason for transfer..."></textarea>
                        </div>
                        <div class="mb-5 fv-row">
                            <label for="transfer_date" class="form-label required">Transaction Date</label>
                            <input type="date" class="form-control" id="transfer_date" name="transaction_date" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="mb-5 fv-row">
                            <label for="transfer_proof" class="form-label">Transfer Slip / Proof Photo</label>
                            <input type="file" class="form-control" id="transfer_proof" accept="image/*">
                            <div class="form-text">Optional. Image only, max 5 MB. Attached to the source-account leg of the transfer.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="transferSubmitBtn" class="btn btn-info">
                            <span class="indicator-label">Transfer Funds</span>
                            <span class="indicator-progress">Please wait...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}">
@endsection
@section('vendor-scripts')
    <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
@endsection

@section('scripts')
    
    <script>
        $(document).ready(function() {
            // Current balance tracking
            let currentBalance = {{ $bank->balance }};

            // Initialize DataTable
            var transactionsTable = $('#transactionsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('banks.transactions', $bank->id) }}',
                columns: [
                    { data: 'reference_number', name: 'reference_number' },
                    { data: 'formatted_date', name: 'transaction_date' },
                    { data: 'type_badge', name: 'type' },
                    { data: 'payee_display', name: 'payee' },
                    { data: 'description', name: 'description' },
                    { data: 'formatted_amount', name: 'amount', className: 'text-end' },
                    { data: 'formatted_balance', name: 'balance_after', className: 'text-end' },
                    { data: 'proof', name: 'proof', orderable: false, searchable: false, className: 'text-center' },
                    { data: 'created_by_name', name: 'created_by' },
                ],
                order: [[1, 'desc']],
                pageLength: 25,
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'copy',
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
                    },
                    {
                        extend: 'excel',
                        title: '{{ $bank->account_name }} - Transactions',
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
                    },
                    {
                        extend: 'csv',
                        title: '{{ $bank->account_name }} - Transactions',
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
                    },
                    {
                        extend: 'pdf',
                        title: '{{ $bank->account_name }} - Transactions',
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
                    }
                ],
                initComplete: function() {
                    // Move buttons to hidden container for custom menu
                    this.api().buttons().container().appendTo('#datatable_buttons');
                }
            });

            // Search functionality
            $('#tableSearch').keyup(function() {
                transactionsTable.search($(this).val()).draw();
            });

            // Export menu handlers
            $('[data-kt-export="copy"]').on('click', function() {
                transactionsTable.button('.buttons-copy').trigger();
            });
            $('[data-kt-export="excel"]').on('click', function() {
                transactionsTable.button('.buttons-excel').trigger();
            });
            $('[data-kt-export="csv"]').on('click', function() {
                transactionsTable.button('.buttons-csv').trigger();
            });
            $('[data-kt-export="pdf"]').on('click', function() {
                transactionsTable.button('.buttons-pdf').trigger();
            });

            // =====================
            // DEPOSIT FORM VALIDATION
            // =====================
            const depositForm = document.querySelector('#depositForm');
            const depositSubmitBtn = document.querySelector('#depositSubmitBtn');

            const depositValidator = validateForm(depositForm, {
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
                        }
                    }
                },
                payee: {
                    validators: {
                        stringLength: {
                            max: 255,
                            message: 'Depositor/Source must be less than 255 characters'
                        }
                    }
                },
                description: {
                    validators: {
                        stringLength: {
                            max: 500,
                            message: 'Description must be less than 500 characters'
                        }
                    }
                },
                transaction_date: {
                    validators: {
                        notEmpty: {
                            message: 'Transaction date is required'
                        },
                        date: {
                            format: 'YYYY-MM-DD',
                            message: 'Please enter a valid date'
                        }
                    }
                }
            });

            depositSubmitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                depositValidator.validate().then(function(status) {
                    if (status === 'Valid') {
                        disableSubmitFormButton(depositSubmitBtn);
                        submitTransaction(
                            '{{ route('banks.deposit', $bank->id) }}',
                            $('#depositForm'),
                            '#depositModal',
                            depositSubmitBtn,
                            depositValidator,
                            document.getElementById('deposit_proof').files[0]
                        );
                    }
                });
            });

            // =====================
            // WITHDRAWAL FORM VALIDATION
            // =====================
            const withdrawalForm = document.querySelector('#withdrawalForm');
            const withdrawalSubmitBtn = document.querySelector('#withdrawalSubmitBtn');

            const withdrawalValidator = validateForm(withdrawalForm, {
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
                            message: 'Insufficient balance. Available: ' + currentBalance.toLocaleString('en-US', {minimumFractionDigits: 2}),
                            callback: function(input) {
                                return parseFloat(input.value) <= currentBalance;
                            }
                        }
                    }
                },
                payee: {
                    validators: {
                        stringLength: {
                            max: 255,
                            message: 'Payee/Recipient must be less than 255 characters'
                        }
                    }
                },
                description: {
                    validators: {
                        stringLength: {
                            max: 500,
                            message: 'Description must be less than 500 characters'
                        }
                    }
                },
                transaction_date: {
                    validators: {
                        notEmpty: {
                            message: 'Transaction date is required'
                        },
                        date: {
                            format: 'YYYY-MM-DD',
                            message: 'Please enter a valid date'
                        }
                    }
                }
            });

            withdrawalSubmitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                withdrawalValidator.validate().then(function(status) {
                    if (status === 'Valid') {
                        disableSubmitFormButton(withdrawalSubmitBtn);
                        submitTransaction(
                            '{{ route('banks.withdrawal', $bank->id) }}',
                            $('#withdrawalForm'),
                            '#withdrawalModal',
                            withdrawalSubmitBtn,
                            withdrawalValidator,
                            document.getElementById('withdrawal_proof').files[0]
                        );
                    }
                });
            });

            // =====================
            // TRANSFER FORM VALIDATION
            // =====================
            const transferForm = document.querySelector('#transferForm');
            const transferSubmitBtn = document.querySelector('#transferSubmitBtn');

            const transferValidator = validateForm(transferForm, {
                transfer_to_bank_id: {
                    validators: {
                        notEmpty: {
                            message: 'Please select a destination account'
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
                            message: 'Insufficient balance. Available: ' + currentBalance.toLocaleString('en-US', {minimumFractionDigits: 2}),
                            callback: function(input) {
                                return parseFloat(input.value) <= currentBalance;
                            }
                        }
                    }
                },
                description: {
                    validators: {
                        stringLength: {
                            max: 500,
                            message: 'Description must be less than 500 characters'
                        }
                    }
                },
                transaction_date: {
                    validators: {
                        notEmpty: {
                            message: 'Transaction date is required'
                        },
                        date: {
                            format: 'YYYY-MM-DD',
                            message: 'Please enter a valid date'
                        }
                    }
                }
            });

            transferSubmitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                transferValidator.validate().then(function(status) {
                    if (status === 'Valid') {
                        disableSubmitFormButton(transferSubmitBtn);
                        submitTransaction(
                            '{{ route('banks.transfer', $bank->id) }}',
                            $('#transferForm'),
                            '#transferModal',
                            transferSubmitBtn,
                            transferValidator,
                            document.getElementById('transfer_proof').files[0]
                        );
                    }
                });
            });

            // =====================
            // FORM SUBMISSION HANDLER
            // =====================
            function submitTransaction(url, form, modalId, submitBtn, validator, proofFile) {
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        if (!response.success) {
                            enableSubmitFormButton(submitBtn);
                            return;
                        }

                        const finishUp = function () {
                            $(modalId).modal('hide');
                            form[0].reset();
                            form.find('input[name="transaction_date"]').val('{{ date('Y-m-d') }}');
                            validator.resetForm();
                            transactionsTable.ajax.reload();

                            // Update balance displays.
                            currentBalance = parseFloat(response.new_balance);
                            const formattedBalance = currentBalance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            $('#currentBalance').text(formattedBalance);
                            $('#withdrawalAvailableBalance').text(formattedBalance);
                            $('#transferAvailableBalance').text(formattedBalance);

                            if (currentBalance <= 0) {
                                $('#currentBalance').removeClass('text-primary').addClass('text-danger');
                            } else {
                                $('#currentBalance').removeClass('text-danger').addClass('text-primary');
                            }

                            successSwal('Success', response.message);
                            enableSubmitFormButton(submitBtn);
                        };

                        // If the user picked a proof slip in the modal, upload it to the
                        // newly-created transaction before closing the modal so they get
                        // a single "saved" toast.
                        if (proofFile && response.transaction_id) {
                            const fd = new FormData();
                            fd.append('proof', proofFile);
                            fd.append('_token', $('meta[name=csrf-token]').attr('content'));
                            $.ajax({
                                url: '/admin/bank-transactions/' + response.transaction_id + '/proof',
                                method: 'POST',
                                data: fd,
                                contentType: false,
                                processData: false,
                                success: finishUp,
                                error: function (xhr) {
                                    enableSubmitFormButton(submitBtn);
                                    errorSwal('Proof upload failed', xhr.responseJSON?.message || 'The transaction was saved, but the proof slip could not be uploaded.');
                                },
                            });
                            return;
                        }

                        finishUp();
                    },
                    error: function(xhr) {
                        enableSubmitFormButton(submitBtn);
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

            // Reset form validation when modals are hidden
            $('#depositModal').on('hidden.bs.modal', function() {
                depositForm.reset();
                depositValidator.resetForm();
                $('#deposit_date').val('{{ date('Y-m-d') }}');
            });

            $('#withdrawalModal').on('hidden.bs.modal', function() {
                withdrawalForm.reset();
                withdrawalValidator.resetForm();
                $('#withdrawal_date').val('{{ date('Y-m-d') }}');
            });

            $('#transferModal').on('hidden.bs.modal', function() {
                transferForm.reset();
                transferValidator.resetForm();
                $('#transfer_date').val('{{ date('Y-m-d') }}');
            });

            // Hidden file picker triggered by per-row "Upload" buttons in the Proof column.
            const proofInput = $('<input type="file" accept="image/*" style="display:none;">').appendTo('body');
            let proofTargetTransactionId = null;
            $(document).on('click', '.upload-proof-btn', function () {
                proofTargetTransactionId = $(this).data('transaction-id');
                proofInput.val('').trigger('click');
            });
            proofInput.on('change', function () {
                if (!this.files || !this.files[0] || !proofTargetTransactionId) return;
                const fd = new FormData();
                fd.append('proof', this.files[0]);
                fd.append('_token', $('meta[name=csrf-token]').attr('content'));
                $.ajax({
                    url: '/admin/bank-transactions/' + proofTargetTransactionId + '/proof',
                    method: 'POST',
                    data: fd,
                    contentType: false,
                    processData: false,
                    success: function (resp) {
                        if (resp.success) {
                            transactionsTable.ajax.reload(null, false);
                        } else {
                            alert(resp.message || 'Upload failed.');
                        }
                    },
                    error: function (xhr) {
                        alert(xhr.responseJSON?.message || 'Upload failed.');
                    },
                });
            });
        });
    </script>
@endsection
