@extends('layout.app')
@section('header')
    - Expenses
@endsection
@section('title')
    Expenses
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">Expenses</li>
@endsection
@section('actions')
    <x-data-table.actions :show-export="false">
        <div class="px-5 py-3">
            <div class="fs-5 text-dark fw-bold">Export Options</div>
        </div>
        <div class="menu-item px-3">
            <a href="{{ route('expenses.export') }}" class="menu-link px-3">Export as CSV</a>
        </div>
    </x-data-table.actions>
    <x-general.search-table title="Expense"></x-general.search-table>
@endsection
@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-6">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('expense_categories.index') }}" class="btn btn-outline btn-outline-dashed btn-active-light-info">
                <i class="ki-duotone ki-category fs-5 me-1">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                Categories
            </a>
            <button class="btn btn-primary" id="expenseCreateButton">
                <i class="ki-duotone ki-plus fs-5"></i>
                Record Expense
            </button>
        </div>
    </div>
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <x-data-table.table table-id="expensesTable">
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Payee</th>
                        <th>Category</th>
                        <th>Branch</th>
                        <th>Bank Account</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                        <th>Receipt</th>
                        <th>By</th>
                        <th></th>
                    </x-data-table.table>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('modals')
    <!-- Create/Edit Expense Modal -->
    <div class="modal fade" id="expenseModal" tabindex="-1" aria-labelledby="expenseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="expenseForm" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="expenseModalLabel">
                            <i class="ki-duotone ki-wallet fs-2 me-2 text-primary">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                            <span id="expenseModalType">Record</span> Expense
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-5 fv-row">
                                    <label for="bank_id" class="form-label required">Bank Account</label>
                                    <select class="form-select" id="bank_id" name="bank_id" data-control="select2" data-dropdown-parent="#expenseModal" data-placeholder="Select bank account...">
                                        <option></option>
                                        @foreach($banks as $bank)
                                            <option value="{{ $bank->id }}" data-balance="{{ $bank->balance }}">
                                                {{ $bank->account_name }} - {{ $bank->bank_name }} (Bal: {{ number_format($bank->balance, 2) }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text" id="bankBalanceInfo"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-5 fv-row">
                                    <label for="amount" class="form-label required">Amount</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-5 fv-row">
                                    <label for="payee" class="form-label required">Payee / Vendor</label>
                                    <input type="text" class="form-control" id="payee" name="payee" placeholder="e.g., Electric Company, Supplier Name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-5 fv-row">
                                    <label for="expense_date" class="form-label required">Expense Date</label>
                                    <input type="date" class="form-control" id="expense_date" name="expense_date" value="{{ date('Y-m-d') }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-5 fv-row">
                                    <label for="expense_category_id" class="form-label">Category</label>
                                    <select class="form-select" id="expense_category_id" name="expense_category_id" data-control="select2" data-dropdown-parent="#expenseModal" data-placeholder="Select category..." data-allow-clear="true">
                                        <option></option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-5 fv-row">
                                    <label for="store_id" class="form-label">Branch / Store</label>
                                    <select class="form-select" id="store_id" name="store_id" data-control="select2" data-dropdown-parent="#expenseModal" data-placeholder="Select branch..." data-allow-clear="true">
                                        <option></option>
                                        @foreach($stores as $store)
                                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-5 fv-row">
                                    <label for="receipt_number" class="form-label">Receipt / Invoice Number</label>
                                    <input type="text" class="form-control" id="receipt_number" name="receipt_number" placeholder="Optional">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-5 fv-row">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="2" placeholder="Optional notes..."></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-5 fv-row">
                                    <label for="receipt_photo" class="form-label">Receipt Photo</label>
                                    <input type="file" class="form-control" id="receipt_photo" name="receipt_photo" accept="image/*">
                                    <div class="form-text">Optional. Image only, max 5 MB. Replaces the existing photo when editing.</div>
                                    <div id="existing_receipt_preview" class="mt-3" style="display:none;">
                                        <a id="existing_receipt_link" href="#" target="_blank" rel="noopener">
                                            <img id="existing_receipt_img" alt="Current receipt" style="max-height:120px;border:1px solid #e1e3ea;border-radius:6px;padding:4px;">
                                        </a>
                                        <button type="button" id="remove_receipt_btn" class="btn btn-sm btn-light-danger ms-3">Remove receipt</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="expenseSubmitBtn" class="btn btn-primary">
                            <span class="indicator-label" id="expenseButtonType">Record Expense</span>
                            <span class="indicator-progress">Please wait...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Expense Modal -->
    <x-modals.delete
        identifier="expense"
        title-identifier="Expense"
    ></x-modals.delete>
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
            const url = '/admin/expenses';
            const identifier = 'expense';

            // Elements
            const modal = $('#expenseModal');
            const createButton = $('#expenseCreateButton');
            const modalTitleType = $('#expenseModalType');
            const modalButtonType = $('#expenseButtonType');
            const form = document.querySelector('#expenseForm');
            const submitButton = document.querySelector('#expenseSubmitBtn');
            const table = $('#expensesTable');

            let id = null;
            let validator;
            let currentBalance = 0;

            // Initialize
            init();

            function init() {
                $('body').tooltip({selector: '[data-bs-toggle="tooltip"]'});
                initTable();
                initValidation();
                handlers();
            }

            function initTable() {
                $('#tableSearch').keyup(function() {
                    table.DataTable().search($(this).val()).draw();
                });

                table.DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: url + '/table',
                    columns: [
                        { data: 'reference_number', name: 'reference_number' },
                        { data: 'formatted_date', name: 'expense_date' },
                        { data: 'payee', name: 'payee' },
                        { data: 'category_name', name: 'category.name' },
                        { data: 'store_name', name: 'store.name' },
                        { data: 'bank_name', name: 'bank.account_name' },
                        { data: 'formatted_amount', name: 'amount', className: 'text-end' },
                        { data: 'status_badge', name: 'status' },
                        { data: 'receipt', name: 'receipt', orderable: false, searchable: false, className: 'text-center' },
                        { data: 'created_by_name', name: 'createdBy.name' },
                        { data: 'actions', name: 'actions', orderable: false, searchable: false },
                    ],
                    order: [[1, 'desc']],
                    pageLength: 25,
                });

            }

            function initValidation() {
                validator = validateForm(form, {
                    bank_id: {
                        validators: {
                            notEmpty: {
                                message: 'Please select a bank account'
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
                                message: 'Insufficient bank balance',
                                callback: function(input) {
                                    if (id) return true; // Skip for edit mode
                                    return parseFloat(input.value) <= currentBalance;
                                }
                            }
                        }
                    },
                    payee: {
                        validators: {
                            notEmpty: {
                                message: 'Payee/Vendor is required'
                            },
                            stringLength: {
                                max: 255,
                                message: 'Payee name must be less than 255 characters'
                            }
                        }
                    },
                    expense_date: {
                        validators: {
                            notEmpty: {
                                message: 'Expense date is required'
                            },
                            date: {
                                format: 'YYYY-MM-DD',
                                message: 'Please enter a valid date'
                            }
                        }
                    },
                    description: {
                        validators: {
                            stringLength: {
                                max: 1000,
                                message: 'Description must be less than 1000 characters'
                            }
                        }
                    },
                    receipt_number: {
                        validators: {
                            stringLength: {
                                max: 100,
                                message: 'Receipt number must be less than 100 characters'
                            }
                        }
                    }
                });
            }

            function handlers() {
                // Bank selection change - update balance info
                $('#bank_id').on('change', function() {
                    const selectedOption = $(this).find(':selected');
                    currentBalance = parseFloat(selectedOption.data('balance')) || 0;
                    if (currentBalance > 0) {
                        $('#bankBalanceInfo').html('<span class="text-success">Available: ' + currentBalance.toLocaleString('en-US', {minimumFractionDigits: 2}) + '</span>');
                    } else {
                        $('#bankBalanceInfo').html('<span class="text-danger">No balance available</span>');
                    }
                    // Revalidate amount field
                    validator.revalidateField('amount');
                });

                // Create button click
                createButton.on('click', function() {
                    modalTitleType.text('Record');
                    modalButtonType.text('Record Expense');
                    form.setAttribute('method', 'POST');
                    id = null;
                    // Enable bank and amount fields for new expense
                    $('#bank_id').prop('disabled', false);
                    $('#amount').prop('disabled', false);
                    modal.modal('show');
                });

                // Modal hidden - reset form
                modal.on('hidden.bs.modal', function() {
                    form.reset();
                    validator.resetForm();
                    $('#expense_date').val('{{ date('Y-m-d') }}');
                    $('#bank_id').val('').trigger('change');
                    $('#expense_category_id').val('').trigger('change');
                    $('#store_id').val('').trigger('change');
                    $('#bankBalanceInfo').html('');
                    $('#receipt_photo').val('');
                    $('#existing_receipt_preview').hide();
                    currentBalance = 0;
                    id = null;
                });

                // "Remove receipt" button inside the modal — clears the photo on the saved expense.
                $('#remove_receipt_btn').on('click', function () {
                    if (!id) return;
                    if (!confirm('Remove this receipt photo? This cannot be undone.')) return;
                    $.ajax({
                        url: url + '/' + id + '/receipt',
                        method: 'DELETE',
                        data: { _token: '{{ csrf_token() }}' },
                        success: function () {
                            $('#existing_receipt_preview').hide();
                            refreshTable(table);
                        },
                        error: function (xhr) {
                            errorSwal('Error', xhr.responseJSON?.message || 'Failed to remove receipt.');
                        },
                    });
                });

                // Form submit
                submitButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    validator.validate().then(function(status) {
                        if (status === 'Valid') {
                            disableSubmitFormButton(submitButton);
                            submitExpenseForm();
                        }
                    });
                });

                // Edit button click
                table.on('click', '.btn-active-color-info', function() {
                    id = $(this).val();
                    modalTitleType.text('Edit');
                    modalButtonType.text('Update Expense');
                    form.setAttribute('method', 'PUT');
                    form.setAttribute('method_id', id);
                    // Disable bank and amount for edit (can't change financial data)
                    $('#bank_id').prop('disabled', true);
                    $('#amount').prop('disabled', true);

                    // Load expense data
                    $.ajax({
                        url: url + '/get/' + id,
                        type: 'GET',
                        success: function(response) {
                            const expense = response.expense;
                            $('#bank_id').val(expense.bank_id).trigger('change');
                            $('#amount').val(expense.amount);
                            $('#payee').val(expense.payee);
                            $('#expense_date').val(expense.expense_date);
                            $('#expense_category_id').val(expense.expense_category_id).trigger('change');
                            $('#store_id').val(expense.store_id).trigger('change');
                            $('#receipt_number').val(expense.receipt_number);
                            $('#description').val(expense.description);

                            // Show existing receipt thumbnail when one is attached.
                            if (expense.receipt_photo) {
                                const url = '{{ asset('') }}' + expense.receipt_photo;
                                $('#existing_receipt_link').attr('href', url);
                                $('#existing_receipt_img').attr('src', url);
                                $('#existing_receipt_preview').show();
                            } else {
                                $('#existing_receipt_preview').hide();
                            }
                            $('#receipt_photo').val('');

                            modal.modal('show');
                        },
                        error: function(response) {
                            errorSwal('Error', response.responseJSON?.message || 'Failed to load expense data');
                        }
                    });
                });

                // Delete handler
                handleDelete(
                    'Expense',
                    table,
                    $('#deleteExpenseModal'),
                    document.querySelector('#btnDeleteExpense'),
                    document.querySelector('#deleteExpenseForm'),
                    url
                );
            }

            function submitExpenseForm() {
                const formUrl = id ? url + '/' + id : url;
                const method = id ? 'PUT' : 'POST';

                $.ajax({
                    url: formUrl,
                    type: method,
                    data: {
                        _token: '{{ csrf_token() }}',
                        expense_category_id: $('#expense_category_id').val() || null,
                        store_id: $('#store_id').val() || null,
                        bank_id: $('#bank_id').val(),
                        payee: $('#payee').val(),
                        amount: $('#amount').val(),
                        expense_date: $('#expense_date').val(),
                        description: $('#description').val(),
                        receipt_number: $('#receipt_number').val(),
                    },
                    success: function(response) {
                        if (!response.success) {
                            errorSwal('Error', response.message);
                            enableSubmitFormButton(submitButton);
                            return;
                        }

                        // If a new receipt photo was selected, upload it to the saved expense
                        // before closing the modal so the user gets a single "saved" toast.
                        const expenseId = id || response.expense?.id;
                        const file = document.getElementById('receipt_photo').files[0];
                        if (file && expenseId) {
                            const fd = new FormData();
                            fd.append('receipt', file);
                            fd.append('_token', '{{ csrf_token() }}');
                            $.ajax({
                                url: url + '/' + expenseId + '/receipt',
                                method: 'POST',
                                data: fd,
                                contentType: false,
                                processData: false,
                                success: function () {
                                    modal.modal('hide');
                                    refreshTable(table);
                                    successSwal('Success', response.message);
                                    enableSubmitFormButton(submitButton);
                                },
                                error: function (xhr) {
                                    enableSubmitFormButton(submitButton);
                                    errorSwal('Receipt upload failed', xhr.responseJSON?.message || 'The expense was saved, but the receipt could not be uploaded.');
                                },
                            });
                            return;
                        }

                        modal.modal('hide');
                        refreshTable(table);
                        successSwal('Success', response.message);
                        enableSubmitFormButton(submitButton);
                    },
                    error: function(xhr) {
                        enableSubmitFormButton(submitButton);
                        let errors = xhr.responseJSON?.errors;
                        if (errors) {
                            let errorMsg = Object.values(errors).flat().join('\n');
                            errorSwal('Validation Error', errorMsg);
                        } else {
                            errorSwal('Error', xhr.responseJSON?.message || 'An error occurred');
                        }
                    }
                });
            }

            // Hidden file picker triggered by per-row "Upload" buttons in the Receipt column.
            const receiptInput = $('<input type="file" accept="image/*" style="display:none;">').appendTo('body');
            let receiptTargetExpenseId = null;
            $(document).on('click', '.upload-receipt-btn', function () {
                receiptTargetExpenseId = $(this).data('expense-id');
                receiptInput.val('').trigger('click');
            });
            receiptInput.on('change', function () {
                if (!this.files || !this.files[0] || !receiptTargetExpenseId) return;
                const fd = new FormData();
                fd.append('receipt', this.files[0]);
                fd.append('_token', $('meta[name=csrf-token]').attr('content'));
                $.ajax({
                    url: '/admin/expenses/' + receiptTargetExpenseId + '/receipt',
                    method: 'POST',
                    data: fd,
                    contentType: false,
                    processData: false,
                    success: function (resp) {
                        if (resp.success) {
                            datatable.ajax.reload(null, false);
                        } else {
                            errorSwal('Error', resp.message || 'Upload failed.');
                        }
                    },
                    error: function (xhr) {
                        errorSwal('Error', xhr.responseJSON?.message || 'Upload failed.');
                    },
                });
            });
        });
    </script>
@endsection
