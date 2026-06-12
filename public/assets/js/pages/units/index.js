$(document).ready(function() {
    // Prevent submitting form when hitting Enter on keyboard
    $(window).keydown(function(event){
        if(event.keyCode == 13) {
            event.preventDefault();
            return false;
        }
    });

    let identifier = 'unit';
    let url = '/admin/units';
    let titleIdentifiers = 'Unit'

    let modal = $('#' + identifier + 'Modal');
    let createButton = $('#' + identifier +'CreateButton');
    let modalTitleType = $('#' + identifier +'ModalType');
    let modalButtonType = $('#' + identifier +'ButtonType');
    let form = document.querySelector('#' + identifier +'Form');
    let submitButton = document.querySelector('#btnSubmitCreateEditForm');

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
        let validator = validateForm(
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
                            name: $('input[name=name]').val(),
                        },
                        submitButton,
                        table,
                        // leave blank if there are no methods to be executed.
                        function methods(){
                            //
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
                    $('input[name=name]').val(response.name);
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
                {data: 'actions'},
            ],
            columnDefs: [
                {
                    targets: 0,
                    render: function (data, type, full) {
                        return `<span class="fw-semibold text-uppercase">${full.name}</span>`;
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
        initExportButtons();
    }

    function initExportButtons(){
        const documentTitle = 'Units';
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


