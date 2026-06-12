$(document).ready(function() {
    // Prevent submitting form when hitting Enter on keyboard
    $(window).keydown(function(event){
        if(event.keyCode == 13) {
            event.preventDefault();
            return false;
        }
    });

    let identifier = 'category';
    let url = '/admin/categories';
    let titleIdentifiers = 'Category'

    let modal = $('#' + identifier + 'Modal');
    let createButton = $('#' + identifier +'CreateButton');
    let modalTitleType = $('#' + identifier +'ModalType');
    let modalButtonType = $('#' + identifier +'ButtonType');
    let form = document.querySelector('#' + identifier +'Form');
    let submitButton = document.querySelector('#btnSubmitCreateEditForm');

    let table = $('#' + identifier +'Table')
    let id;
    let defaultImageUrl = '/assets/media/svg/shapes/abstract-4-dark.svg';

    init();

    function init(){
        // Enable tooltip function
        $('body').tooltip({selector: '[data-bs-toggle="tooltip"]'});
        // Load Table
        initTable();
        // Event Handlers
        handlers();
    }

    function handlers(){
        createButton.on('click', function () {
            modalTitleType.text('Create');
            modalButtonType.text('Create');
            form.setAttribute('method', 'POST');
            resetImagePreview();
            modal.modal('show');
        });

        modal.on('hidden.bs.modal', function () {
            form.reset();
            resetImagePreview();
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

    function resetImagePreview() {
        $('#categoryImagePreview').css('background-image', 'url(' + defaultImageUrl + ')');
        $('input[name=old_image]').val('');
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
                    submitCategoryForm(title);
                } else {
                    enableSubmitFormButton(submitButton);
                }
            })
        });
    }

    function submitCategoryForm(title) {
        let formData = new FormData();
        formData.append('name', $('input[name=name]').val());
        formData.append('description', $('textarea[name=description]').val() || '');
        formData.append('icon', $('input[name=icon]').val() || '');

        let imageFile = $('input[name=image]')[0].files[0];
        if (imageFile) {
            formData.append('image', imageFile);
        }

        let oldImage = $('input[name=old_image]').val();
        if (oldImage) {
            formData.append('old_image', oldImage);
        }

        let methodId = form.getAttribute('method_id');
        let method = form.getAttribute('method');
        let requestUrl = methodId ? url + '/' + methodId : url;

        if (method === 'PUT') {
            formData.append('_method', 'PUT');
        }

        $.ajax({
            url: requestUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response){
                if(response.success === true){
                    modal.modal('hide');
                    successSwal(title, response.message);
                    refreshTable(table);
                    id = null;
                } else {
                    errorSwal('Error', response.message);
                }
                enableSubmitFormButton(submitButton);
                form.removeAttribute('method_id');
                form.reset();
                resetImagePreview();
            },
            error: function (response){
                console.log(response);
                let errors = "";
                let message = "";
                if(response.responseJSON.errors)
                    Object.values(response.responseJSON.errors).forEach(error => {
                        errors += error[0] + ` \n`;
                    })
                message = (response.responseJSON.errors) ? errors : response.responseJSON.message;
                enableSubmitFormButton(submitButton);
                errorSwal('Error', message);
            }
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
                    $('textarea[name=description]').val(response.description || '');
                    $('input[name=icon]').val(response.icon || '');
                    if (response.image) {
                        $('#categoryImagePreview').css('background-image', 'url(' + response.image + ')');
                        $('input[name=old_image]').val(response.image);
                    } else {
                        resetImagePreview();
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
                {data: 'actions'},
            ],
            columnDefs: [
                {
                    targets: 0,
                    render: function (data, type, full) {
                        let iconDisplay = full.icon ? `<span class="fs-2 me-3">${full.icon}</span>` : '';
                        let imageDisplay = full.image ? `<img src="/${full.image}" class="rounded me-3" style="width:40px;height:40px;object-fit:cover;" />` : '';
                        return `<div class="d-flex align-items-center">
                            ${iconDisplay || imageDisplay}
                            <div>
                                <span class="fw-semibold text-uppercase">${full.name}</span>
                                ${full.description ? '<br><small class="text-muted">' + full.description.substring(0, 60) + (full.description.length > 60 ? '...' : '') + '</small>' : ''}
                            </div>
                        </div>`;
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
        const documentTitle = 'Categories';
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
