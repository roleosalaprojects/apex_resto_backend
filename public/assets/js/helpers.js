let moneyFormat = wNumb({
    prefix: '₱ ',
    decimals: 2,
    thousand: ','
});

function accountingFormat(x) {
    return x.toFixed(2).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function convertNumberShorter(x){
    let num = x.toString();
    if(x > 999999999){
        let suffix = ' B';
        if(x > 9999999999){
            return num[0] + num[1] + num[2] + suffix;
        }else if(x > 999999999){
            return num[0] + num[1] + suffix;
        }
        return num[0] + suffix;
    }
    else if(x > 999999){
        let suffix = ' M';
        if(x > 99999999){
            return num[0] + num[1] + num[2] + suffix;
        }else if(x > 9999999){
            return num[0] + num[1] + suffix;
        }
        return num[0] + suffix;
    }else if(x > 999){
        let suffix = ' T';
        if(x > 99999){
            return num[0] + num[1] + num[2] + suffix;
        }else if(x > 9999){
            return num[0] + num[1] + suffix;
        }
        return num[0] + suffix;
    }else{
        return x;
    }
}

function createSwal(html){
    return {
        html: html,
        icon: "info",
        showCancelButton: true,
        buttonsStyling: false,
        confirmButtonText: "Yes, create it!",
        cancelButtonText: "No, return",
        customClass: {
            confirmButton: "btn btn-primary",
            cancelButton: "btn btn-active-light"
        }
    }
}

function infoSwalCustom(html){
    return {
        html: html,
        icon: "info",
        showCancelButton: true,
        buttonsStyling: false,
        confirmButtonText: "View",
        cancelButtonText: "Back",
        customClass: {
            confirmButton: "btn btn-primary",
            cancelButton: "btn btn-active-light"
        }
    }
}

function validateForm(form, fields){
    return FormValidation.formValidation(
        form,
        {
            fields: fields,
            plugins: {
                trigger: new FormValidation.plugins.Trigger(),
                bootstrap: new FormValidation.plugins.Bootstrap5({
                    rowSelector: '.fv-row',
                    eleInvalidClass: '',
                    eleValidClass: '',
                })
            }
        }
    )
}

function submitForm(modal, title, url, form, data, submitButton, table, methods){
    $.ajax({
        url: form.getAttribute('method_id') ? url + '/' +form.getAttribute('method_id') : url,
        type: form.getAttribute('method'),
        data: data,
        success: function(response){
            if(response.success === true){
                modal.modal('hide');
                successSwal(title, response.message);
                refreshTable(table);
                id = null;
            }else{
                errorSwal('Error', response.message);
            }
            enableSubmitFormButton(submitButton);
            form.removeAttribute('method_id');
            form.reset();
            //Fire methods if request is successful.
            methods();
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
    })
}

function handleDelete(title, table, modal, submitButton, form, url){
  console.log('handle delete')
    //Initialize Variables
    let id;
    let input = modal.find($('input[name=delete_name]'));

    modal.find($("#deleteModalTitle")).text(title);
    table.on('click', '.btn-active-color-danger', function(){
        id = $(this).val();
        input.val($("#name_"+id).val());
        modal.modal('show');
    });
    // Submit Button Handler
    submitButton.addEventListener('click', function(e){
        e.preventDefault();
        if(input.val()){
            submitForm(modal, title, url + '/' +id, form, null, submitButton, table, ()=>{});
        }else{
            input.addClass('is-invalid');
            $('.text-danger').removeClass('d-none');
            $('#edit_message').html("Cannot be blank!");
        }
    });
}

function enableSubmitFormButton(submitButton){
    submitButton.removeAttribute('data-kt-indicator');
    submitButton.disabled = false;
}

function disableSubmitFormButton(submitButton){
    submitButton.setAttribute('data-kt-indicator', 'on');
    submitButton.disabled = true;
}

// Reload DataTable
function refreshTable(table){
    table.DataTable().ajax.reload();
}

/*
* For Information Use, using SweetAlert
* */
function successSwal(title, message){
    Swal.fire({
        title: title,
        text: `${message}`,
        icon: 'success',
        buttonsStyling: false,
        confirmButtonText: "Ok, got it!",
        customClass: {
            confirmButton: "btn btn-primary"
        }
    });
}

function infoSwal(title, message){
    Swal.fire({
        title: title,
        text: `${message}`,
        icon: 'info',
        buttonsStyling: false,
        confirmButtonText: "Ok, got it!",
        customClass: {
            confirmButton: "btn btn-primary"
        }
    });
}

function defaultErrorSwal(title){
    Swal.fire({
        title: title,
        text: "Sorry, looks like there are some errors detected, please try again.",
        icon: "error",
        buttonsStyling: false,
        confirmButtonText: "Ok, got it!",
        customClass: {
            confirmButton: "btn btn-primary"
        }
    });
}

function errorSwal(title, message){
    Swal.fire({
        title: title,
        text: message,
        icon: "error",
        buttonsStyling: false,
        confirmButtonText: "Ok, got it!",
        customClass: {
            confirmButton: "btn btn-primary"
        }
    });
}

function toastrOptions(){
    if (typeof toastr !== 'undefined') {
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": true,
            "progressBar": true,
            "positionClass": "toastr-top-right",
            "preventDuplicates": false,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };
    }
}

function limitDecimalPlaces(e, count) {
    if (e.target.value.indexOf('.') == -1) { return; }
    if ((e.target.value.length - e.target.value.indexOf('.')) > count) {
        e.target.value = parseFloat(e.target.value).toFixed(count);
    } else {
        return 0;
    }
}

function isNumberKey(evt) {
    var charCode = (evt.which) ? evt.which : evt.keyCode;
    if (charCode != 26 && charCode > 31 && (charCode < 28 || charCode > 57))
        return false;
    return true;
}

function TSeparators(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
