$(document).ready(function() {
    // Prevent submitting form when hitting Enter on keyboard
    $(window).keydown(function(event){
        if(event.keyCode == 13) {
            event.preventDefault();
            return false;
        }
    });

    let identifier = 'customer';
    let url = '/admin/special_customers';
    let titleIdentifiers = 'Customer'

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
        // Other Styles
        Inputmask({
            'placeholder': '000-000-000-00000',
            'mask': '999-999-999-99999',
            'type': 'text',
        }).mask('#tin')
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
            modal.modal('show');
        });

        modal.on('hidden.bs.modal', function () {
            form.reset();
        })

        formEventHandlers(
            'Category',
            form,
            {
                name: {
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
                identifier: {
                    validators: {
                        notEmpty: {
                            message: `${titleIdentifiers} identification is required.`
                        },
                        stringLength: {
                            max: 255,
                            message: `${titleIdentifiers} identification too long.`
                        }
                    }
                },
                tin: {
                    validators: {
                        notEmpty: {
                            message: `${titleIdentifiers} TIN is required.`
                        },
                        stringLength: {
                            max: 18,
                            message: `${titleIdentifiers} TIN too long.`
                        }
                    }
                },
                child_name: {
                    validators: {
                        stringLength: {
                            max: 255,
                            message: `${titleIdentifiers} child name too long.`
                        },
                        callback: {
                            message: `${titleIdentifiers} child name is required.`,
                            callback: function(input) {
                                if($('#type').find(":selected").val() === 2){
                                    if(input.value.length > 0){
                                        return true;
                                    }
                                    return false;
                                }else{
                                    return true;
                                }
                            }
                        }
                    }
                },
                child_age: {
                    validators: {
                        stringLength: {
                            max: 2,
                            message: `${titleIdentifiers} child age too long.`
                        },
                        callback: {
                            message: `${titleIdentifiers} child age is required.`,
                            callback: function(input) {
                                if($('#type').find(":selected").val() === 2){
                                    if(input.value > 0){
                                        return true;
                                    }
                                    return false;
                                }else{
                                    return true;
                                }
                            }
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
        customFormHandlers();
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
                            name: $('input[name=name]').val(),
                            identifier: $('input[name=identifier]').val(),
                            tin: $('input[name=tin]').val(),
                            type: $('#type').find(':selected').val(),
                            child_name: $('input[name=child_name]').val(),
                            child_age: $('input[name=child_age]').val(),
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
                    // TODO::Don't forget to remove log
                    let customer = response.customer;
                    $('input[name=name]').val(customer.name);
                    $('input[name=identifier]').val(customer.identifier);
                    $('input[name=tin]').val(customer.tin);
                    $('#type option[value=' + customer.type + ']').prop('selected', true);
                    if(customer.type == 2){
                        $('#solo_parent_options').removeClass('d-none');
                        $('input[name=child_name]').val(customer.child_name);
                        $('input[name=child_age]').val(customer.child_age);
                    }
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
                {data: 'name'},
                {data: 'identifier'},
                {data: 'type'},
                {data: 'tin'},
                {data: 'actions'},
            ],
            columnDefs: [
                {
                    targets: 0,
                    render: function (data, type, full) {
                        return `<span class="fw-semibold text-uppercase font-monospace">${full.name}</span>`;
                    }
                },
                {
                    targets: 2,
                    render: function (data, type, full) {
                        let types = {
                            0: {
                                name: 'Senior Citizen',
                            },
                            1: {
                                name: 'Persons with Disability',
                            },
                            2: {
                                name: 'Solo Parent',
                            },
                            3: {
                                name: 'Nation Athletes and Coaches',
                            },
                            null: [
                                name = 'N/A',
                            ]
                        }
                        return `<span class="fw-semibold text-uppercase font-monospace">
                            ${types[full.type]['name']}
                            ${full.type === 2
                            ? `<br>Child: ${full.child_name}<br>Age: ${full.child_age}`
                            : ``}</span>`;
                    }
                },
            ],
            ajax: {
                dataSrc: function (response){
                    return response.data
                },
                url: url + '/table'
            },
        }
        table.DataTable(option);
    }

    function customFormHandlers(){
        let soloParentOptions = $('#solo_parent_options');
        form.querySelector('#type').addEventListener('change', function (e) {
            if(e.target.value == 2){
                soloParentOptions.removeClass('d-none');
            }else{
                soloParentOptions.addClass('d-none');
            }
        })
    }
})


