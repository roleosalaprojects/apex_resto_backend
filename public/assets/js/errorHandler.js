function errorHandler(errors){
    $("#danger_description").html(errors);
    $("#alert_danger").removeClass('d-none');
}

// Handlers
function validate(input, label){
    if(!input.val()){
        input.addClass("is-invalid");
        label.removeClass('d-none');
        label.html("Cannot be blank!");
        return false;
    }else{
        input.removeClass("is-invalid");
        label.addClass('d-none');
        label.html(null);
        return true;
    }
}

// // Validate Jquery Fields
// function validateFields(input, tag){
//     if(!input.val() || input.val() == null){
//         input.addClass('is-invalid');
//         input.after("<span class='text-danger " + tag + "-field'>Cannot be blank!</span>")
//         return false;
//     }else{
//         input.removeClass('is-invalid')
//         input.closest("tr").find("." + tag + "-field").remove();
//         return true;
//     }
// }

function Response(response, message, source){
    if(response[0] == 200){
        $('#success_title').html("Successfully " + message + "!");
        $("#success_description").html("Successfully added a new "+ source +" "+response[1]+".");
        $("#alert_success").removeClass('d-none');
        $('input[name=name]').removeClass('is-invalid');
        $('.text-danger').addClass('d-none');
        $('#name_message').html("Cannot be blank!");
        $("#genericTable").DataTable().ajax.reload();
        setTimeout(function() {
            $('#alert_success').addClass('d-none');
        }, 4000);
        return true;
    }else{
        return false;
    }
}

let checker = arr => arr.every(v => v === true);
