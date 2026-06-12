@extends('layout.app')
@section('header')
    - Bulk Edit Products
@endsection
@section('title')
    Item/Product Bulk Edit
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('items.index') }}">Items</a></li>
    <li class="breadcrumb-item pe-3 text-muted">Bulk Edit</li>
@endsection
@section('actions')
@endsection
@section('content')
    <div class="row g-5">
        {{-- Item Selection Card --}}
        <div class="col-12">
            <div class="card card-flush">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="fw-bold">Item Selection</h3>
                    </div>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-sm btn-light-primary me-2" id="btnSelectAll">
                            <i class="fas fa-check-double"></i> Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-light-danger" id="btnClearSelection">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Search and Select Items</label>
                            <select class="form-select" id="itemSelect" multiple="multiple" data-placeholder="Search items by name or barcode..."></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filter by Category</label>
                            <select class="form-select" id="categoryFilter">
                                <option value="">All Categories</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-light-info" id="btnAddFromCategory">
                                <i class="fas fa-plus"></i> Add Category Items
                            </button>
                        </div>
                    </div>

                    <div class="alert alert-info d-flex align-items-center">
                        <i class="fas fa-info-circle fs-2 me-3"></i>
                        <div>
                            <strong>Selected Items:</strong> <span id="selectedCount">0</span>
                        </div>
                    </div>

                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-hover table-row-bordered gy-3 gs-5 border rounded" id="selectedItemsTable">
                            <thead class="sticky-top bg-light">
                                <tr class="fw-bold fs-6 text-gray-800">
                                    <th style="width: 50px;"></th>
                                    <th>Barcode</th>
                                    <th>Name</th>
                                    <th class="text-end">Cost</th>
                                    <th class="text-end">Markup</th>
                                    <th class="text-end">Price</th>
                                    <th>Category</th>
                                </tr>
                            </thead>
                            <tbody id="selectedItemsBody">
                                <tr id="noItemsRow">
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fas fa-box-open fs-1 mb-3 d-block opacity-25"></i>
                                        No items selected. Use the search above to add items.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Operations Tabs Card --}}
        <div class="col-12">
            <div class="card card-flush">
                <div class="card-header">
                    <ul class="nav nav-tabs nav-line-tabs nav-stretch fs-6 border-0" id="operationTabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#priceUpdateTab">
                                <i class="fas fa-dollar-sign me-2"></i> Price Update
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#categoryTab">
                                <i class="fas fa-tags me-2"></i> Category Assignment
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#importExportTab">
                                <i class="fas fa-file-csv me-2"></i> Import / Export
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="operationTabsContent">
                        {{-- Price Update Tab --}}
                        <div class="tab-pane fade show active" id="priceUpdateTab">
                            <form id="priceUpdateForm">
                                <div class="row g-5">
                                    <div class="col-md-3">
                                        <label class="form-label required">Field</label>
                                        <select class="form-select" name="field" id="priceField" required>
                                            <option value="price">Price</option>
                                            <option value="cost">Cost</option>
                                            <option value="markup">Markup</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label required">Update Type</label>
                                        <div class="d-flex gap-3 mt-2">
                                            <label class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="radio" name="update_type" value="fixed" checked>
                                                <span class="form-check-label">Fixed Amount</span>
                                            </label>
                                            <label class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="radio" name="update_type" value="percentage">
                                                <span class="form-check-label">Percentage</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label required">Value</label>
                                        <div class="input-group">
                                            <span class="input-group-text" id="valuePrefix">P</span>
                                            <input type="number" class="form-control" name="value" id="priceValue" step="0.01" min="0" required placeholder="0.00">
                                            <span class="input-group-text d-none" id="valueSuffix">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label required">Direction</label>
                                        <div class="d-flex gap-3 mt-2">
                                            <label class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="radio" name="direction" value="increase" checked>
                                                <span class="form-check-label text-success"><i class="fas fa-arrow-up"></i> Increase</span>
                                            </label>
                                            <label class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="radio" name="direction" value="decrease">
                                                <span class="form-check-label text-danger"><i class="fas fa-arrow-down"></i> Decrease</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="separator my-5"></div>
                                <div class="d-flex justify-content-end gap-3">
                                    <button type="button" class="btn btn-light-primary" id="btnPreviewPrices">
                                        <i class="fas fa-eye"></i> Preview Changes
                                    </button>
                                    <button type="submit" class="btn btn-primary" id="btnApplyPrices">
                                        <i class="fas fa-check"></i> Apply to Selected Items
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- Category Assignment Tab --}}
                        <div class="tab-pane fade" id="categoryTab">
                            <form id="categoryUpdateForm">
                                <div class="row g-5">
                                    <div class="col-md-6">
                                        <label class="form-label required">Target Category</label>
                                        <select class="form-select" name="category_id" id="targetCategory" required>
                                            <option value="">Select a category...</option>
                                            @foreach($categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="separator my-5"></div>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary" id="btnApplyCategory">
                                        <i class="fas fa-check"></i> Apply to Selected Items
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- Import/Export Tab --}}
                        <div class="tab-pane fade" id="importExportTab">
                            <div class="row g-5">
                                {{-- Export Section --}}
                                <div class="col-md-6">
                                    <div class="card card-bordered h-100">
                                        <div class="card-header">
                                            <h4 class="card-title"><i class="fas fa-file-export me-2"></i> Export</h4>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted">Export items to CSV file. You can export selected items or all items.</p>
                                            <div class="d-flex gap-3 mt-5">
                                                <a href="{{ route('products.export-csv') }}" class="btn btn-light-success" id="btnExportSelected">
                                                    <i class="fas fa-download"></i> Export Selected
                                                </a>
                                                <a href="{{ route('products.export-csv') }}" class="btn btn-success">
                                                    <i class="fas fa-download"></i> Export All
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Import Section --}}
                                <div class="col-md-6">
                                    <div class="card card-bordered h-100">
                                        <div class="card-header">
                                            <h4 class="card-title"><i class="fas fa-file-import me-2"></i> Import</h4>
                                        </div>
                                        <div class="card-body">
                                            <form id="importForm" enctype="multipart/form-data">
                                                <div class="mb-5">
                                                    <a href="{{ route('products.import-template') }}" class="btn btn-sm btn-light-info">
                                                        <i class="fas fa-file-download"></i> Download Template
                                                    </a>
                                                </div>
                                                <div class="mb-5">
                                                    <label class="form-label">CSV File</label>
                                                    <input type="file" class="form-control" name="file" id="importFile" accept=".csv,.txt" required>
                                                    <div class="form-text">Max file size: 10MB</div>
                                                </div>
                                                <div class="mb-5">
                                                    <label class="form-check form-check-custom form-check-solid">
                                                        <input class="form-check-input" type="checkbox" name="update_existing" id="updateExisting" value="1">
                                                        <span class="form-check-label">Update existing items (match by barcode)</span>
                                                    </label>
                                                </div>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-upload"></i> Import
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Progress Modal --}}
    <div class="modal fade" id="progressModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-spinner fa-spin me-2" id="progressSpinner"></i>
                        <i class="fas fa-check-circle text-success me-2 d-none" id="progressComplete"></i>
                        <i class="fas fa-times-circle text-danger me-2 d-none" id="progressFailed"></i>
                        <span id="progressTitle">Processing...</span>
                    </h5>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" role="progressbar" style="width: 0%">0%</div>
                        </div>
                    </div>
                    <div class="text-muted small">
                        <span id="processedCount">0</span> / <span id="totalCount">0</span> records processed
                    </div>
                    <div class="mt-3 d-none" id="progressResults">
                        <div class="alert alert-light p-3">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-check text-success"></i> Success:</span>
                                <span id="successCount">0</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-times text-danger"></i> Failed:</span>
                                <span id="failedCount">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 d-none" id="errorsList">
                        <h6 class="text-danger">Errors:</h6>
                        <div class="bg-light p-3 rounded" style="max-height: 150px; overflow-y: auto;">
                            <ul class="mb-0 small" id="errorsListContent"></ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-none" id="progressFooter">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Preview Modal --}}
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Preview Price Changes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-hover table-row-bordered">
                            <thead class="sticky-top bg-light">
                                <tr>
                                    <th>Name</th>
                                    <th class="text-end">Current</th>
                                    <th class="text-end">New</th>
                                    <th class="text-end">Change</th>
                                </tr>
                            </thead>
                            <tbody id="previewBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}">
    <style>
        .select2-container--default .select2-selection--multiple {
            min-height: 42px;
            border: 1px solid #e4e6ef;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #009ef7;
            border: none;
            color: #fff;
            padding: 2px 8px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #fff;
            margin-right: 5px;
        }
    </style>
@endsection

@section('vendor-scripts')
    <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
@endsection

@section('scripts')
<script>
$(function() {
    let selectedItems = new Map();
    let progressInterval = null;

    // Initialize Select2 for item search
    $('#itemSelect').select2({
        ajax: {
            url: '{{ route("items.select") }}',
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return { term: params.term };
            },
            processResults: function(data) {
                return { results: data };
            },
            cache: true
        },
        minimumInputLength: 1,
        allowClear: true,
        placeholder: 'Search items by name or barcode...',
        width: '100%'
    });

    // Handle item selection
    $('#itemSelect').on('select2:select', function(e) {
        const item = e.params.data;
        addItemToSelection(item.id, item.text);
        $(this).val(null).trigger('change');
    });

    // Add item to selection
    function addItemToSelection(id, name) {
        if (selectedItems.has(id)) return;

        // Fetch full item details
        $.get(`/admin/items/get/${id}`, function(item) {
            selectedItems.set(id, item);
            renderSelectedItems();
        });
    }

    // Remove item from selection
    function removeItemFromSelection(id) {
        selectedItems.delete(id);
        renderSelectedItems();
    }

    // Render selected items table
    function renderSelectedItems() {
        const tbody = $('#selectedItemsBody');
        tbody.empty();

        if (selectedItems.size === 0) {
            tbody.html(`
                <tr id="noItemsRow">
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="fas fa-box-open fs-1 mb-3 d-block opacity-25"></i>
                        No items selected. Use the search above to add items.
                    </td>
                </tr>
            `);
        } else {
            selectedItems.forEach((item, id) => {
                tbody.append(`
                    <tr data-id="${id}">
                        <td>
                            <button type="button" class="btn btn-sm btn-icon btn-light-danger btn-remove-item" data-id="${id}">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                        <td>${item.barcode || '-'}</td>
                        <td>${item.name}</td>
                        <td class="text-end" data-cost="${item.cost}">${formatCurrency(item.cost)}</td>
                        <td class="text-end" data-markup="${item.markup}">${item.markup}%</td>
                        <td class="text-end" data-price="${item.price}">${formatCurrency(item.price)}</td>
                        <td>${item.category?.name || '-'}</td>
                    </tr>
                `);
            });
        }

        $('#selectedCount').text(selectedItems.size);
        updateExportLink();
    }

    // Remove item button click
    $(document).on('click', '.btn-remove-item', function() {
        const id = $(this).data('id');
        removeItemFromSelection(id);
    });

    // Clear all selection
    $('#btnClearSelection').click(function() {
        selectedItems.clear();
        renderSelectedItems();
    });

    // Select all from category
    $('#btnAddFromCategory').click(function() {
        const categoryId = $('#categoryFilter').val();
        if (!categoryId) {
            Swal.fire('Info', 'Please select a category first', 'info');
            return;
        }

        $.get('{{ route("items.table") }}', { category: categoryId }, function(response) {
            response.data.forEach(item => {
                if (!selectedItems.has(item.id)) {
                    selectedItems.set(item.id, item);
                }
            });
            renderSelectedItems();
        });
    });

    // Update type change handler
    $('input[name="update_type"]').change(function() {
        const isPercentage = $(this).val() === 'percentage';
        $('#valuePrefix').toggleClass('d-none', isPercentage);
        $('#valueSuffix').toggleClass('d-none', !isPercentage);
    });

    // Preview price changes
    $('#btnPreviewPrices').click(function() {
        if (selectedItems.size === 0) {
            Swal.fire('Warning', 'Please select at least one item', 'warning');
            return;
        }

        const field = $('#priceField').val();
        const updateType = $('input[name="update_type"]:checked').val();
        const value = parseFloat($('#priceValue').val()) || 0;
        const direction = $('input[name="direction"]:checked').val();

        const previewBody = $('#previewBody');
        previewBody.empty();

        selectedItems.forEach((item, id) => {
            const currentValue = parseFloat(item[field]) || 0;
            let change = updateType === 'percentage' ? currentValue * (value / 100) : value;
            if (direction === 'decrease') change = -change;
            const newValue = Math.max(0, currentValue + change);

            const changeClass = change >= 0 ? 'text-success' : 'text-danger';
            const changePrefix = change >= 0 ? '+' : '';

            previewBody.append(`
                <tr>
                    <td>${item.name}</td>
                    <td class="text-end">${field === 'markup' ? currentValue + '%' : formatCurrency(currentValue)}</td>
                    <td class="text-end fw-bold">${field === 'markup' ? newValue.toFixed(2) + '%' : formatCurrency(newValue)}</td>
                    <td class="text-end ${changeClass}">${changePrefix}${field === 'markup' ? change.toFixed(2) + '%' : formatCurrency(change)}</td>
                </tr>
            `);
        });

        new bootstrap.Modal('#previewModal').show();
    });

    // Price update form submit
    $('#priceUpdateForm').submit(function(e) {
        e.preventDefault();

        if (selectedItems.size === 0) {
            Swal.fire('Warning', 'Please select at least one item', 'warning');
            return;
        }

        const data = {
            item_ids: Array.from(selectedItems.keys()),
            field: $('#priceField').val(),
            update_type: $('input[name="update_type"]:checked').val(),
            value: parseFloat($('#priceValue').val()) || 0,
            direction: $('input[name="direction"]:checked').val()
        };

        Swal.fire({
            title: 'Confirm Update',
            text: `Are you sure you want to update ${selectedItems.size} items?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, update'
        }).then((result) => {
            if (result.isConfirmed) {
                submitBulkOperation('{{ route("products.bulk-update-prices") }}', data, 'Price Update');
            }
        });
    });

    // Category update form submit
    $('#categoryUpdateForm').submit(function(e) {
        e.preventDefault();

        if (selectedItems.size === 0) {
            Swal.fire('Warning', 'Please select at least one item', 'warning');
            return;
        }

        const categoryId = $('#targetCategory').val();
        if (!categoryId) {
            Swal.fire('Warning', 'Please select a category', 'warning');
            return;
        }

        const data = {
            item_ids: Array.from(selectedItems.keys()),
            category_id: categoryId
        };

        Swal.fire({
            title: 'Confirm Update',
            text: `Are you sure you want to update category for ${selectedItems.size} items?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, update'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("products.bulk-update-category") }}',
                    method: 'POST',
                    data: data,
                    success: function(response) {
                        Swal.fire('Success', response.message, 'success');
                        refreshSelectedItems();
                    },
                    error: function(xhr) {
                        Swal.fire('Error', xhr.responseJSON?.message || 'An error occurred', 'error');
                    }
                });
            }
        });
    });

    // Import form submit
    $('#importForm').submit(function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        showProgressModal('Import');

        $.ajax({
            url: '{{ route("products.import-csv") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.log_id) {
                    pollProgress(response.log_id);
                }
            },
            error: function(xhr) {
                hideProgressModal();
                Swal.fire('Error', xhr.responseJSON?.message || 'Import failed', 'error');
            }
        });
    });

    // Submit bulk operation
    function submitBulkOperation(url, data, title) {
        showProgressModal(title);

        $.ajax({
            url: url,
            method: 'POST',
            data: data,
            success: function(response) {
                if (response.async && response.log_id) {
                    pollProgress(response.log_id);
                } else {
                    updateProgressComplete(response);
                    refreshSelectedItems();
                }
            },
            error: function(xhr) {
                hideProgressModal();
                Swal.fire('Error', xhr.responseJSON?.message || 'Operation failed', 'error');
            }
        });
    }

    // Show progress modal
    function showProgressModal(title) {
        $('#progressTitle').text(title + '...');
        $('#progressSpinner').removeClass('d-none');
        $('#progressComplete').addClass('d-none');
        $('#progressFailed').addClass('d-none');
        $('#progressBar').css('width', '0%').text('0%');
        $('#processedCount').text('0');
        $('#totalCount').text('0');
        $('#progressResults').addClass('d-none');
        $('#errorsList').addClass('d-none');
        $('#progressFooter').addClass('d-none');
        new bootstrap.Modal('#progressModal').show();
    }

    // Hide progress modal
    function hideProgressModal() {
        bootstrap.Modal.getInstance(document.getElementById('progressModal'))?.hide();
    }

    // Poll progress
    function pollProgress(logId) {
        progressInterval = setInterval(function() {
            $.get(`/admin/products/bulk-operation/${logId}/status`, function(data) {
                updateProgress(data);

                if (data.status === 'completed' || data.status === 'failed') {
                    clearInterval(progressInterval);
                    progressInterval = null;

                    if (data.status === 'completed') {
                        $('#progressSpinner').addClass('d-none');
                        $('#progressComplete').removeClass('d-none');
                        $('#progressTitle').text('Completed');
                        refreshSelectedItems();
                    } else {
                        $('#progressSpinner').addClass('d-none');
                        $('#progressFailed').removeClass('d-none');
                        $('#progressTitle').text('Failed');
                    }

                    $('#progressFooter').removeClass('d-none');
                }
            });
        }, 1000);
    }

    // Update progress display
    function updateProgress(data) {
        const percent = data.progress_percent || 0;
        $('#progressBar').css('width', percent + '%').text(percent + '%');
        $('#processedCount').text(data.processed_records);
        $('#totalCount').text(data.total_records);

        if (data.success_records > 0 || data.failed_records > 0) {
            $('#progressResults').removeClass('d-none');
            $('#successCount').text(data.success_records);
            $('#failedCount').text(data.failed_records);
        }

        if (data.errors && data.errors.length > 0) {
            $('#errorsList').removeClass('d-none');
            $('#errorsListContent').html(
                data.errors.slice(0, 10).map(e => `<li>${e.message || e.row ? 'Row ' + e.row + ': ' + e.message : JSON.stringify(e)}</li>`).join('')
            );
        }
    }

    // Update progress for sync operations
    function updateProgressComplete(response) {
        $('#progressSpinner').addClass('d-none');
        $('#progressComplete').removeClass('d-none');
        $('#progressTitle').text('Completed');
        $('#progressBar').css('width', '100%').text('100%').removeClass('progress-bar-animated');
        $('#processedCount').text(response.updated || 0);
        $('#totalCount').text(response.updated || 0);
        $('#progressResults').removeClass('d-none');
        $('#successCount').text(response.updated || 0);
        $('#failedCount').text(response.failed || 0);
        $('#progressFooter').removeClass('d-none');

        if (response.errors && response.errors.length > 0) {
            $('#errorsList').removeClass('d-none');
            $('#errorsListContent').html(
                response.errors.map(e => `<li>Item ${e.item_id}: ${e.message}</li>`).join('')
            );
        }
    }

    // Refresh selected items data
    function refreshSelectedItems() {
        const ids = Array.from(selectedItems.keys());
        if (ids.length === 0) return;

        const promises = ids.map(id =>
            $.get(`/admin/items/get/${id}`).then(item => ({ id, item }))
        );

        Promise.all(promises).then(results => {
            selectedItems.clear();
            results.forEach(({ id, item }) => {
                selectedItems.set(id, item);
            });
            renderSelectedItems();
        }).catch(error => {
            console.error('Error refreshing items:', error);
        });
    }

    // Update export link with selected item IDs
    function updateExportLink() {
        const ids = Array.from(selectedItems.keys());
        let url = '{{ route("products.export-csv") }}';
        if (ids.length > 0) {
            url += '?' + ids.map(id => 'item_ids[]=' + id).join('&');
        }
        $('#btnExportSelected').attr('href', url);
    }

    // Format currency
    function formatCurrency(value) {
        return 'P' + parseFloat(value || 0).toFixed(2);
    }

    // Initialize
    renderSelectedItems();
});
</script>
@endsection
