function successSwal(title, message){
    Swal.fire({
        title: title,
        text: `${message}`,
        icon: 'success',
        buttonsStyling: false,
        confirmButtonText: "Ok, got it!",
        customClass: {
            confirmButton: "btn btn-success"
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
            confirmButton: "btn btn-info"
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