@php
    $branding = app(\App\Services\BrandingService::class)->forStorefront();
    $brandName = $branding['brand_name'] ?: 'Quick Baskets';
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="light" data-bs-theme="light">
<head>
    <base href=""/>
    <title>{{ $brandName }} - Verify Your Email</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="shortcut icon" href="{{asset('assets/media/logos/favicon.ico')}}" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <link href="{{asset('assets/plugins/global/plugins.bundle.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('assets/css/style.bundle.css')}}" rel="stylesheet" type="text/css" />
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
            <div class="card-body p-10 text-center">
                @if (! empty($branding['logo_url']))
                    <img src="{{ $branding['logo_url'] }}" alt="{{ $brandName }}" class="mb-3" style="max-height: 60px;">
                @else
                    <h1 class="fw-bolder mb-3" style="color: var(--qb-primary);">{{ $brandName }}</h1>
                @endif
                <h3 class="fw-bold mb-5" style="color: #1a1a2e;">Verify Your Email Address</h3>

                @if (session('status') == 'verification-link-sent')
                    <div class="alert alert-success d-flex align-items-center mb-8" style="border-radius: 8px;">
                        <i class="ki-duotone ki-check-circle fs-2x me-3 text-success"><span class="path1"></span><span class="path2"></span></i>
                        <div>A new verification link has been sent to your email address.</div>
                    </div>
                @elseif (session('success'))
                    <div class="alert alert-info d-flex align-items-center mb-8" style="border-radius: 8px;">
                        <div>{{ session('success') }}</div>
                    </div>
                @endif

                <p class="text-gray-700 fs-5 mb-8">
                    Thanks for signing up! Before getting started, please verify your email address by clicking the link we just sent to
                    <strong>{{ auth('customer')->user()->email }}</strong>.
                    If you did not receive the email, you can request another below.
                </p>

                <div class="d-grid mb-5">
                    <form method="POST" action="{{ route('customer.verification.send') }}">
                        @csrf
                        <button type="submit" class="btn fw-bold w-100" style="background: var(--qb-primary); color: {{ $branding['on_primary'] }}; border-radius: 8px; padding: 12px;">
                            Resend Verification Email
                        </button>
                    </form>
                </div>

                <form method="POST" action="{{ route('customer.logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-link text-gray-500 fw-semibold text-decoration-none">
                        Log Out
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="{{asset('assets/plugins/global/plugins.bundle.js')}}"></script>
<script src="{{asset('assets/js/scripts.bundle.js')}}"></script>
</body>
</html>
