@php
    $branding = app(\App\Services\BrandingService::class)->forStorefront();
    $brandName = $branding['brand_name'] ?: 'Quick Baskets';
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="light" data-bs-theme="light">
<head>
    <base href=""/>
    <title>{{ $brandName }} - Sign In</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="shortcut icon" href="{{asset('assets/media/logos/favicon.ico')}}" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <link href="{{asset('assets/plugins/global/plugins.bundle.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('assets/css/style.bundle.css')}}" rel="stylesheet" type="text/css" />
    {{-- Tenant brand palette. Hex values guarded by BrandingService::sanitizeHex(). --}}
    <style>
        :root {
            --qb-primary: {{ $branding['primary'] }};
            --qb-primary-dark: {{ $branding['secondary'] }};
            --qb-accent: {{ $branding['accent'] }};
            --bs-primary: {{ $branding['primary'] }};
        }
    </style>
</head>
<body id="kt_body" class="app-blank" style="background: linear-gradient(135deg, var(--qb-primary), var(--qb-primary-dark)); background-attachment: fixed; min-height: 100vh;">
<script>
    document.documentElement.setAttribute('data-theme', 'light');
    document.documentElement.setAttribute('data-bs-theme', 'light');
</script>
<div class="d-flex flex-column flex-root" id="kt_app_root">
    <div class="d-flex flex-center flex-column flex-column-fluid p-10">
        <div class="card w-md-500px" style="border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
            <div class="card-body p-10">
                <form class="form w-100" novalidate="novalidate" id="customer_sign_in_form" action="{{ route('customer.login.submit') }}" method="POST">
                    @csrf
                    <div class="text-center mb-11">
                        @if (! empty($branding['logo_url']))
                            <img src="{{ $branding['logo_url'] }}" alt="{{ $brandName }}" class="mb-3" style="max-height: 60px;">
                        @else
                            <h1 class="fw-bolder mb-3" style="color: var(--qb-primary);">{{ $brandName }}</h1>
                        @endif
                        <h3 class="fw-bold mb-3" style="color: #1a1a2e;">Customer Sign In</h3>
                    </div>
                    @if ($errors->any())
                        <div class="alert alert-danger d-flex align-items-center mb-8" style="border-radius: 8px;">
                            <i class="ki-duotone ki-shield-cross fs-2x me-3 text-danger"><span class="path1"></span><span class="path2"></span></i>
                            <div>
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="fv-row mb-8">
                        <input type="text" placeholder="Email" name="email" value="{{ old('email') }}" autocomplete="off" class="form-control bg-transparent @error('email') is-invalid @enderror" style="border-radius: 8px;" required/>
                        @error('email')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                    <div class="fv-row mb-8">
                        <input type="password" placeholder="Password" name="password" autocomplete="off" class="form-control bg-transparent @error('password') is-invalid @enderror" style="border-radius: 8px;" required/>
                        @error('password')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                    <div class="fv-row mb-8">
                        <label class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                            <span class="form-check-label fw-semibold text-gray-700 fs-base ms-1">Remember Me</span>
                        </label>
                    </div>
                    <div class="d-flex justify-content-end mb-10">
                        <a href="{{ route('customer.register') }}" class="page-link" style="color: var(--qb-primary);">Don't have an account? Register</a>
                    </div>
                    <div class="d-grid mb-10">
                        <button type="submit" id="customer_sign_in_submit" class="btn fw-bold" style="background: var(--qb-primary); color: {{ $branding['on_primary'] }}; border-radius: 8px; padding: 12px;">
                            <span class="indicator-label">Sign In</span>
                            <span class="indicator-progress">Please wait...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                    <div class="text-center">
                        <a href="{{ route('shops.') }}" class="text-gray-500 fw-semibold text-decoration-none">Back to Shop</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="{{asset('assets/plugins/global/plugins.bundle.js')}}"></script>
<script src="{{asset('assets/js/scripts.bundle.js')}}"></script>
<script src="{{asset('assets/js/custom/landing.js')}}"></script>
<script>
    $(document).ready(function(){
        var form, submitButton, validator;
        form = document.querySelector("#customer_sign_in_form");
        submitButton = document.querySelector("#customer_sign_in_submit");
        validator = FormValidation.formValidation(
            form,
            {
                fields: {
                    'email': {
                        validators: {
                            notEmpty: { message: "Email is required." },
                            emailAddress: { message: "Invalid email address." }
                        }
                    },
                    'password': {
                        validators: {
                            notEmpty: { message: "Password is required." }
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

        submitButton.addEventListener('click', function(e){
            e.preventDefault();
            validator.validate().then(function (status){
                if(status == 'Valid'){
                    submitButton.setAttribute('data-kt-indicator', 'on');
                    submitButton.disabled = true;
                    form.submit();
                }
            });
        });
    })
</script>
</body>
</html>
