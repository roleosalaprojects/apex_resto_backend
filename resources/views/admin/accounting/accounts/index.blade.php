@extends('layout.app')
@section('header')
    - Cash Methods
@endsection
@section('title')
    Chart of Accounts
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3 text-muted">Cash Methods</li>
@endsection
@section('actions')
    <x-data-table.actions></x-data-table.actions>
    <!--begin::Menu 1-->
    <div data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-trigger="hover" title=""
         data-bs-original-title="Click to add a cash account">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#accountModal" id="btnCreate">
            Create
        </button>
    </div>
    <input type="text" id="tableSearch" class="form-control form-control-sm" placeholder="Search table">
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <x-data-table.table
                            table-id="paymentsTable"
                    >
                        <th>Account Name</th>
                        <th>Type</th>
                        <th>Balance</th>
                        <th></th>
                    </x-data-table.table>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('modals')
    @include('admin.accounting.accounts._fields')
    @include('admin.accounting.accounts.delete')
@endsection
@section('vendor-styles')
    <link rel="stylesheet" href="{{asset("assets/plugins/custom/datatables/datatables.bundle.css")}}">
@endsection
@section('vendor-scripts')
    {{-- DataTables --}}
    <script src="{{asset("assets/plugins/custom/datatables/datatables.bundle.js")}}"></script>
@endsection
@section('scripts')
    <script src="{{ asset('assets/js/swal.js') }}"></script>
    
    <script>
        $(document).ready(function () {
            let table = $("#paymentsTable");
            let submitButton = document.querySelector("#btnSubmit");
            let modal = $("#accountModal")
            let form = document.querySelector("#accountForm");
            let validator;
            IndexInit();
            __initForms__()

            // Initialization Methods
            function IndexInit() {
                let btnCreate = document.querySelector("#btnCreate");
                let option = {
                    filter: true,
                    responsive: true,
                    serverside: true,
                    processing: true,
                    columns: [
                        {data: 'name'},
                        {data: 'type'},
                        {data: 'current_balance'},
                        {data: 'actions'},
                    ],
                    columnDefs: [
                        // Name
                        {
                            targets: 0,
                            orderable: false,
                            render: function (data, type, full) {
                                return `<span class="fs-5 fw-normal ls-3">${full.name}</span>`
                            }
                        },
                        // Account Type
                        {
                            targets: 1,
                            render: function (data, type, full) {
                                let accountType = {
                                    '1': 'Asset',
                                    '2': 'Liability',
                                    '3': 'Owner\'s Equity',
                                    '4': 'Revenue',
                                    '5': 'Expenses',
                                };
                                let acctType = full.type;
                                return `<span class="fs-5 fw-normal ls-3">${accountType[acctType]}</span>`
                            }
                        },
                        {
                            targets: -1,
                            orderable: false,
                        },
                        {
                            targets: 2,
                            render: function (data, type, full) {
                                return `<span class="fs-5 fw-bold ls-3">${accountingFormat(full.current_balance)}</span>`;
                            }
                        },
                    ],
                    ajax: {
                        dataSrc: function (response) {
                            return response.data
                        },
                        url: '{{ route('accounts.table') }}'
                    },
                }

                let dataTable = table.DataTable(option);
                const documentTitle = 'Cash Accounts';
                var buttons = new $.fn.dataTable.Buttons(table, {
                    buttons: [
                        {
                            extend: 'copyHtml5',
                            title: documentTitle
                        },
                        {
                            extend: 'excelHtml5',
                            title: documentTitle
                        },
                        {
                            extend: 'csvHtml5',
                            title: documentTitle
                        },
                        {
                            extend: 'pdfHtml5',
                            title: documentTitle
                        }
                    ]
                }).container().appendTo($('#datatable_buttons'));

                // Hook dropdown menu click event to datatable export buttons
                const exportButtons = document.querySelectorAll('#datatables_menu [data-kt-export]');
                exportButtons.forEach(exportButton => {
                    exportButton.addEventListener('click', e => {
                        e.preventDefault();

                        // Get clicked export value
                        const exportValue = e.target.getAttribute('data-kt-export');
                        const target = document.querySelector('.dt-buttons .buttons-' + exportValue);

                        // Trigger click event on hidden datatable export buttons
                        target.click();
                    });
                });

                table.on('click', '.btn-active-color-danger', function (e) {
                    var id = $(this).val();
                    var name = $("#name_" + id).val();
                    $('#category_name').html(name);
                    $('#confirm_delete').attr('form', 'form_delete_' + id)
                });
                $('#tableSearch').keyup(function () {
                    table.DataTable().search($(this).val()).draw();
                });

                btnCreate.addEventListener('click', function (e) {
                    $("#accountForm").attr('method', 'POST');
                    $("#accountForm").attr('action', 'accounts/');
                })

                // Edit Event
                table.on('click', '.btn-active-color-info', function () {
                    id = $(this).val();
                    $.ajax({
                        url: '/admin/accounts/' + id,
                        type: "GET",
                        success: function (response) {
                            $("input[name=name]").val(response.name)
                            $("#description").val(response.description)
                            $("input[name=starting_balance]").val(response.starting_balance)
                            $("input[name=current_balance]").val(response.current_balance)
                            $("#type option[value=" + response.type + "]").prop('selected', true)
                            $("input[name=number]").val(response.number)
                            // Modify Form attributes
                            $("#accountForm").attr('method', 'PUT');
                            $("#accountForm").attr('action', 'accounts/' + id);
                        },
                        error: function (response) {
                            errorSwal('Error', response.responseJSON.message);
                        }
                    })
                });

                // Delete Event
                table.on('click', '.btn-active-color-danger', function (e) {
                    id = $(this).val();
                    var name = $("#name_" + id).val();
                    deleteRow(id, name);
                });
            }

            function __initForms__() {
                validator = FormValidation.formValidation(
                    form,
                    {
                        fields: {
                            name: {
                                validators: {
                                    notEmpty: {
                                        message: 'Account name is required.'
                                    }
                                }
                            },
                            starting_balance: {
                                validators: {
                                    numeric: {
                                        message: 'Account balance\'s value must be an integer.',
                                    },
                                    notEmpty: {
                                        message: 'Account balance is required.'
                                    },
                                }
                            },
                            // Current Balance
                            current_balance: {
                                validators: {
                                    numeric: {
                                        message: 'Account balance\'s value must be an integer.',
                                    },
                                    notEmpty: {
                                        message: 'Account balance is required.'
                                    },
                                }
                            },
                            account_type: {
                                validators: {
                                    notEmpty: {
                                        message: 'Account type is required.'
                                    }
                                }
                            }
                        },
                        plugins: {
                            trigger: new FormValidation.plugins.Trigger(),
                            bootstrap: new FormValidation.plugins.Bootstrap5({
                                rowSelector: '.fv-row',
                                eleInvalidClass: "",
                                eleValidClass: "",
                            })
                        }
                    }
                )

                submitButton.addEventListener('click', function (e) {
                    e.preventDefault();
                    console.log($('#type option:selected').val())
                    validator.validate().then(function (status) {
                        if (status == 'Valid') {
                            // Show loading indication
                            submitButton.setAttribute('data-kt-indicator', 'on');
                            // Disable button to avoid multiple click
                            submitButton.disabled = true;
                            $.ajax({
                                type: $("#accountForm").attr('method'),
                                data: {
                                    name: $('input[name=name]').val(),
                                    description: $('#description').val(),
                                    starting_balance: $('input[name=starting_balance]').val(),
                                    current_balance: $('input[name=current_balance]').val(),
                                    type: $('#type option:selected').val(),
                                    number: $('input[name=number]').val(),
                                },
                                url: $("#accountForm").attr('action'),
                                success: function (response) {
                                    console.log(response);
                                    removeLoadingIndicatorOnButton(submitButton);
                                    resetFormHideModal(form, modal)
                                    if (response.success == true) {
                                        successSwal('Account', response.message)
                                        table.DataTable().ajax.reload();
                                    } else {
                                        errorSwal('Something went wrong!', response.message);
                                    }
                                },
                                error: function (response) {
                                    let errors = "";
                                    let message = "";
                                    if (response.responseJSON.errors)
                                        Object.values(response.responseJSON.errors).forEach(error => {
                                            errors += error[0] + ` \n`;
                                        })
                                    message = (response.responseJSON.errors) ? errors : response.responseJSON.message;
                                    removeLoadingIndicatorOnButton(submitButton);
                                    errorSwal('Error', message);
                                }
                            })
                        } else {
                            defaultErrorSwal();
                            removeLoadingIndicatorOnButton(submitButton);
                        }
                    })
                })
            }

            function deleteRow(id, name) {
                var submitButton = $("#btnDeleteCash");
                var modal = $("#deleteCashModal");
                $("input[name=delete_name]").val(name);

                submitButton.click(function () {
                    if (name) {
                        // Show loading indication
                        submitButton.attr('data-kt-indicator', 'on');
                        // Disable button to avoid multiple click
                        submitButton.disabled = true;
                        $.ajax({
                            type: "DELETE",
                            url: "accounts/" + id,
                            success: function (response) {
                                // Hide loading indication
                                submitButton.removeAttr('data-kt-indicator');

                                // Enable button
                                submitButton.disabled = false;
                                if (response.success == 200) {
                                    table.DataTable().ajax.reload();
                                    successSwal("Cash", response.message);
                                } else if (response.statusCode == 500) {
                                    errorSwal('Error', response.message);
                                } else {
                                    // Show error popup. For more info check the plugin's official documentation: https://sweetalert2.github.io/
                                    defaultErrorSwal();
                                }
                            },
                            error: function (response) {
                                submitButton.removeAttr('data-kt-indicator');
                                submitButton.disabled = false;
                                // Show error popup. For more info check the plugin's official documentation: https://sweetalert2.github.io/
                                errorSwal('Error', response.responseJSON.message);
                            }
                        });
                        modal.modal('hide');
                    } else {
                        defaultErrorSwal();
                        removeLoadingIndicatorOnButton(submitButton);
                    }
                });
            }

            function removeLoadingIndicatorOnButton(button) {
                button.removeAttribute('data-kt-indicator');
                button.disabled = false;
            }

            function resetFormHideModal(form, modal) {
                form.reset();
                modal.modal('hide');
            }
        });
    </script>
@endsection
