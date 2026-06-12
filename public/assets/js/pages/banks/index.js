$(document).ready(function() {
    // Prevent submitting form when hitting Enter on keyboard
    $(window).keydown(function(event){
        if(event.keyCode == 13) {
            event.preventDefault();
            return false;
        }
    });

    let identifier = 'bank';
    let url = '/admin/banks';
    let titleIdentifiers = 'Bank'

    let modal = $('#' + identifier + 'Modal');
    let createButton = $('#' + identifier +'CreateButton');
    let modalTitleType = $('#' + identifier +'ModalType');
    let modalButtonType = $('#' + identifier +'ButtonType');
    let form = document.querySelector('#' + identifier +'Form');
    let submitButton = document.querySelector('#btnSubmitCreateEditForm');
    let validator;

    let table = $('#' + identifier +'Table')
    let id;

    init();

    function init(){
        // Enable tooltip function
        $('body').tooltip({selector: '[data-bs-toggle="tooltip"]'});
        // Load Table
        // Initialize Select2
        initTable();
        // Event Handlers
        handlers();
    }

    function handlers(){
        createButton.on('click', function () {
            modalTitleType.text('Create');
            modalButtonType.text('Create');
            form.setAttribute('method', 'POST');
            $('#solo_parent_options').addClass('d-none');
            id = null;
            modal.modal('show');
        });

        modal.on('hidden.bs.modal', function () {
            form.reset();
        })

        formEventHandlers(
            'Category',
            form,
            {
                bank_name: {
                    validators: {
                        notEmpty: {
                            message: `${titleIdentifiers} name is required.`
                        },
                        stringLength: {
                            max: 255,
                            message: `${titleIdentifiers} name too long.`
                        }
                    }
                },
                account_name: {
                    validators: {
                        notEmpty: {
                            message: `${titleIdentifiers} account name is required.`
                        },
                        stringLength: {
                            max: 255,
                            message: `${titleIdentifiers} account name  too long.`
                        }
                    }
                },
                account_number: {
                    validators: {
                        notEmpty: {
                            message: `${titleIdentifiers} account number is required.`
                        },
                        stringLength: {
                            max: 255,
                            message: `${titleIdentifiers} account number too long.`
                        }
                    }
                },
                account_type: {
                    validators: {
                        notEmpty: {
                            message: `${titleIdentifiers} account type is required.`
                        },
                        numeric: {
                            message: `${titleIdentifiers} account type incorrect selection.`
                        }
                    }
                },
                starting_balance: {
                    validators: {
                        notEmpty: {
                            message: `${titleIdentifiers} starting balance is required.`
                        },
                        stringLength: {
                            max: 255,
                            message: `${titleIdentifiers} starting balance too long.`
                        },
                        numeric: {
                            message: `${titleIdentifiers} value is not a number`,
                            thousandsSeparator: '',
                            decimalSeparator: '.',
                        }
                    }
                },
                description: {
                    validators: {
                        notEmpty: {
                            message: `${titleIdentifiers} account description is required.`
                        },
                        stringLength: {
                            max: 255,
                            message: `${titleIdentifiers} account description  too long.`
                        }
                    }
                },
            },
        );
        handleEdit();
        handleDelete(
            titleIdentifiers,
            table,
            $('#delete' + titleIdentifiers + 'Modal'),
            document.querySelector('#btnDelete' + titleIdentifiers),
            document.querySelector('#delete' + titleIdentifiers + 'Form'),
            url
        );
    }

    function formEventHandlers(title, data, fields) {
        validator = validateForm(
            form,
            fields,
        );
        submitButton.addEventListener('click', function (e) {
            e.preventDefault();
            validator.validate().then(function (status) {
                disableSubmitFormButton(submitButton);
                if (status === 'Valid') {
                    submitForm(
                        modal,
                        title,
                        url,
                        form,
                        {
                            id: id,
                            bank_name: $('input[name=bank_name]').val(),
                            account_name: $('input[name=account_name]').val(),
                            account_number: $('input[name=account_number]').val(),
                            account_type: $('#account_type').find(':selected').val(),
                            opening_balance: $('input[name=starting_balance]').val(),
                            balance: $('input[name=starting_balance]').val(),
                            description: $('#description').val(),
                        },
                        submitButton,
                        table,
                        // leave blank if there are no methods to be executed.
                        function methods(){
                            $('#type option[value=0]').prop('selected', true);
                            $('#solo_parent_options').addClass('d-none');
                        },
                    );
                } else {
                    enableSubmitFormButton(submitButton);
                }
            })
        });
    }

    function handleEdit(){
        table.on('click', '.btn-active-color-info', function(){
            // Get ID
            id = $(this).val();
            // Modify form and submit button for Edit & Update (method=PUT)
            modalTitleType.text('Edit');
            modalButtonType.text('Update');
            form.setAttribute('method', 'PUT');
            form.setAttribute('method_id', id);
            modal.modal('show');
            // Get ID Details
            $.ajax({
                url: url + '/get/' + id,
                type: "GET",
                delay: 250,
                success: function(response){
                    let bank = response.bank;
                    $('input[name=bank_name]').val(bank.bank_name);
                    $('input[name=account_name]').val(bank.account_name);
                    $('input[name=account_number]').val(bank.account_number);
                    $('#account_type option[value=' + bank.account_type + ']').prop('selected', true);
                    $('input[name=starting_balance]').val(bank.opening_balance);
                    $('#description').val(bank.description);
                },
                error: function(response){
                    enableSubmitFormButton(submitButton);
                    errorSwal('Error', response.responseJSON.message);
                }
            })
            // Hide Credentials DOM
            $("#credentials").addClass('d-none');
        });
    }

    function initTable(){
        $('#tableSearch').keyup(function(){
            table.DataTable().search($(this).val()).draw();
        });
        let option = {
            filter: true,
            responsive: true,
            serverside: true,
            processing: true,
            columns: [
                {data: 'bank_name'},
                {data: 'account_name'},
                {data: 'account_number'},
                {data: 'balance'},
                {data: 'actions'},
            ],
            columnDefs: [
                {
                    targets: -2,
                    render: function (data, type, full) {
                        return accountingFormat(full.balance);
                    }
                }
            ],
            ajax: {
                dataSrc: function (response){
                    return response.data
                },
                url: url + '/table'
            },
        }
        table.DataTable(option);
        initExportButtons();
    }

    function initExportButtons(){
        const documentTitle = 'Bank Accounts';
        new $.fn.dataTable.Buttons(table, {
            buttons: [
                { extend: 'copyHtml5', title: documentTitle },
                { extend: 'excelHtml5', title: documentTitle },
                { extend: 'csvHtml5', title: documentTitle },
                { extend: 'pdfHtml5', title: documentTitle },
            ]
        }).container().appendTo($('#datatable_buttons'));

        document.querySelectorAll('#datatables_menu [data-kt-export]').forEach(exportButton => {
            exportButton.addEventListener('click', e => {
                e.preventDefault();
                const exportValue = e.target.getAttribute('data-kt-export');
                document.querySelector('.dt-buttons .buttons-' + exportValue).click();
            });
        });
    }
})


