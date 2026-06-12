<x-admin.auth.layout.app>
    @slot('styles') @endslot
    <!--begin::Authentication - Sign-in -->
    <div class="position-absolute top-50 start-50 translate-middle">
        <div class="card card-flush w-md-500px">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row flex-column-fluid">
                    <!--begin::Form-->
                    <form class="form w-100" novalidate="novalidate" id="sign_in_form" action="{{ route('login') }}" method="POST">
                        @csrf
                        <!--begin::Heading-->
                        <div class="text-center mb-11 mt-10">
                            <!--begin::Title-->
                            <h1 class="text-dark fw-bolder mb-3">Quick Baskets</h1>
                            <h3 class="text-dark fw-bolder mb-3">Admin - Sign In</h3>
                            <!--end::Title-->
                        </div>
                        <!--begin::Heading-->
                        <!--begin::Input group=-->
                        <div class="fv-row mb-8">
                            <!--begin::Email-->
                            <input type="text" placeholder="Email" name="email" autocomplete="off" class="form-control bg-transparent @error('email') is-invalid @enderror" required/>
                            <!--end::Email-->
                            @error('email')
                            <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                            @enderror
                        </div>
                        <!--end::Input group=-->
                        <div class="fv-row mb-10">
                            <!--begin::Password-->
                            <input type="password" placeholder="Password" name="password" autocomplete="off" class="form-control bg-transparent @error('email') is-invalid @enderror" required/>
                            <!--end::Password-->
                            @error('password')
                            <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                            @enderror
                        </div>
                        <!--end::Input group=-->
                        <!--begin::Submit button-->
                        <div class="d-grid mb-10">
                            <button type="submit" id="sign_in_submit" class="btn btn-primary">
                                <!--begin::Indicator label-->
                                <span class="indicator-label">Sign In</span>
                                <!--end::Indicator label-->
                                <!--begin::Indicator progress-->
                                <span class="indicator-progress">Please wait...
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                                <!--end::Indicator progress-->
                            </button>
                        </div>
                        <!--end::Submit button-->
                    </form>
                    <!--end::Form-->
                </div>
            </div>
        </div>
    </div>
    <!--end::Authentication - Sign-in-->
    @slot('scripts')
            <script>
                $(document).ready(function(){
                    var form, submitButton, validator;
                    form = document.querySelector("#sign_in_form");
                    submitButton = document.querySelector("#sign_in_submit");
                    validator = FormValidation.formValidation(
                        form,
                        {
                            fields: {
                                'email': {
                                    validators: {
                                        notEmpty: {
                                            message: "Email is required."
                                        },
                                        emailAddress: {
                                            message: "Invalid email address."
                                        }
                                    }
                                },
                                'password': {
                                    validators: {
                                        notEmpty: {
                                            message: "Password is required."
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

                    // Handle Form submit
                    submitButton.addEventListener('click', function(e){
                        // Prevent button default action
                        e.preventDefault();

                        // Validate Form
                        validator.validate().then(function (status){
                            if(status == 'Valid'){
                                // Show loading indication
                                submitButton.setAttribute('data-kt-indicator', 'on');
                                // Disable button to avoid multiple click
                                submitButton.disabled = true;
                                form.submit();
                                // alert('Form is submitted');
                            }
                        });
                    });
                })
            </script>
    @endslot
</x-admin.auth.layout.app>
