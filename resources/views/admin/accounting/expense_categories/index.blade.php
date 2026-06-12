@extends('layout.app')
@section('header')
    - Expense Categories
@endsection
@section('title')
    Expense Categories
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('expenses.index') }}">Expenses</a></li>
    <li class="breadcrumb-item text-muted">Categories</li>
@endsection
@section('actions')
    <x-data-table.actions :show-export="false">
        <div class="px-5 py-3">
            <div class="fs-5 text-dark fw-bold">Export Options</div>
        </div>
        <div class="menu-item px-3">
            <a href="{{ route('expense_categories.export') }}" class="menu-link px-3">Export as CSV</a>
        </div>
    </x-data-table.actions>
    <x-general.search-table title="Category"></x-general.search-table>
@endsection
@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-6">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('expenses.index') }}" class="btn btn-outline btn-outline-dashed btn-active-light-info">
                <i class="ki-duotone ki-arrow-left fs-5 me-1">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Back to Expenses
            </a>
            <button type="button" class="btn btn-primary" id="expense_categoryCreateButton">
                <i class="ki-duotone ki-plus fs-5"></i>
                Create Category
            </button>
        </div>
    </div>
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <x-data-table.table table-id="expense_categoryTable">
                        <th class="min-w-200px">Name</th>
                        <th>Description</th>
                        <th>Expenses</th>
                        <th>Status</th>
                        <th></th>
                    </x-data-table.table>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('modals')
    <x-modals.create-edit
        identifier="expense_category"
        title="Expense Category"
    >
        <div class="mb-5 fv-row">
            <label for="name" class="form-label required">Category Name</label>
            <input type="text" class="form-control" name="name" id="name" placeholder="e.g., Utilities, Office Supplies">
        </div>
        <div class="mb-5 fv-row">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" name="description" id="description" rows="2" placeholder="Optional description..."></textarea>
        </div>
    </x-modals.create-edit>
    <x-modals.delete
        identifier="expense_category"
        title-identifier="Expense Category"
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
            // Prevent form submit on Enter
            $(window).keydown(function(event) {
                if (event.keyCode === 13) {
                    event.preventDefault();
                    return false;
                }
            });

            const identifier = 'expense_category';
            const url = '/admin/expense_categories';
            const titleIdentifiers = 'Expense Category';

            const modal = $('#' + identifier + 'Modal');
            const createButton = $('#' + identifier + 'CreateButton');
            const modalTitleType = $('#' + identifier + 'ModalType');
            const modalButtonType = $('#' + identifier + 'ButtonType');
            const form = document.querySelector('#' + identifier + 'Form');
            const submitButton = document.querySelector('#btnSubmitCreateEditForm');
            const table = $('#' + identifier + 'Table');

            let id = null;
            let validator;

            // Initialize
            init();

            function init() {
                $('body').tooltip({selector: '[data-bs-toggle="tooltip"]'});
                initTable();
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
                        { data: 'name', name: 'name' },
                        { data: 'description', name: 'description' },
                        { data: 'expenses_count', name: 'expenses_count' },
                        { data: 'status_badge', name: 'status' },
                        { data: 'actions', name: 'actions', orderable: false, searchable: false },
                    ],
                    order: [[0, 'asc']],
                    pageLength: 25,
                });

            }

            function handlers() {
                // Create button click
                createButton.on('click', function() {
                    modalTitleType.text('Create');
                    modalButtonType.text('Create');
                    form.setAttribute('method', 'POST');
                    id = null;
                    modal.modal('show');
                });

                // Modal hidden - reset form
                modal.on('hidden.bs.modal', function() {
                    form.reset();
                    if (validator) validator.resetForm();
                    id = null;
                });

                // Form validation and submission
                formEventHandlers();

                // Edit button click
                handleEdit();

                // Delete handler
                handleDelete(
                    titleIdentifiers,
                    table,
                    $('#delete' + titleIdentifiers.replace(/\s+/g, '') + 'Modal'),
                    document.querySelector('#btnDelete' + titleIdentifiers.replace(/\s+/g, '')),
                    document.querySelector('#delete' + titleIdentifiers.replace(/\s+/g, '') + 'Form'),
                    url
                );
            }

            function formEventHandlers() {
                validator = validateForm(form, {
                    name: {
                        validators: {
                            notEmpty: {
                                message: 'Category name is required'
                            },
                            stringLength: {
                                max: 255,
                                message: 'Category name must be less than 255 characters'
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
                    }
                });

                submitButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    validator.validate().then(function(status) {
                        disableSubmitFormButton(submitButton);
                        if (status === 'Valid') {
                            submitForm(
                                modal,
                                titleIdentifiers,
                                url,
                                form,
                                {
                                    id: id,
                                    name: $('input[name=name]').val(),
                                    description: $('#description').val(),
                                },
                                submitButton,
                                table,
                                function methods() {}
                            );
                        } else {
                            enableSubmitFormButton(submitButton);
                        }
                    });
                });
            }

            function handleEdit() {
                table.on('click', '.btn-active-color-info', function() {
                    id = $(this).val();
                    modalTitleType.text('Edit');
                    modalButtonType.text('Update');
                    form.setAttribute('method', 'PUT');
                    form.setAttribute('method_id', id);

                    $.ajax({
                        url: url + '/get/' + id,
                        type: 'GET',
                        success: function(response) {
                            const category = response.category;
                            $('input[name=name]').val(category.name);
                            $('#description').val(category.description);
                            modal.modal('show');
                        },
                        error: function(response) {
                            errorSwal('Error', response.responseJSON?.message || 'Failed to load category data');
                        }
                    });
                });
            }
        });
    </script>
@endsection
