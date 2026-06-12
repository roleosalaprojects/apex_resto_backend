@php
    $branding = app(\App\Services\BrandingService::class)->forStorefront();
    $brandName = $branding['brand_name'] ?: 'Quick Baskets';
    $hexToRgb = function (string $hex): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return preg_match('/^[0-9a-f]{6}$/i', $hex) === 1
            ? implode(', ', sscanf($hex, '%02x%02x%02x'))
            : '255, 140, 105';
    };
    $primaryRgb = $hexToRgb($branding['primary']);
    $secondaryRgb = $hexToRgb($branding['secondary']);
    $accentRgb = $hexToRgb($branding['accent']);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light" data-bs-theme="light">
<head>
    <base href=""/>
    <title>{{ $brandName }} - Customer Portal</title>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <link rel="shortcut icon" href="{{ asset('assets/media/logos/favicon.ico') }}"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700"/>
    <link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('assets/css/style.bundle.css') }}" rel="stylesheet" type="text/css"/>
    <style>
        :root {
            --qb-primary: {{ $branding['primary'] }};
            --qb-primary-dark: {{ $branding['secondary'] }};
            --qb-accent: {{ $branding['accent'] }};
            --qb-primary-rgb: {{ $primaryRgb }};
            --qb-primary-dark-rgb: {{ $secondaryRgb }};
            --qb-accent-rgb: {{ $accentRgb }};
            --qb-bg: #fff8f5;
        }
        body { background-color: var(--qb-bg) !important; }
        .qb-customer-header {
            background: linear-gradient(135deg, var(--qb-primary), var(--qb-primary-dark));
        }
        .qb-btn-primary {
            background-color: var(--qb-primary) !important;
            border-color: var(--qb-primary) !important;
            color: {{ $branding['on_primary'] }} !important;
        }
        .qb-btn-primary:hover {
            background-color: var(--qb-primary-dark) !important;
            border-color: var(--qb-primary-dark) !important;
        }
        .qb-card {
            border-radius: 12px !important;
            border: none !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }
        .qb-card:hover {
            box-shadow: 0 6px 20px rgba(0,0,0,0.10);
            transform: translateY(-2px);
        }
        .qb-price { color: var(--qb-accent); font-weight: 700; }
        .qb-badge-cart {
            background-color: var(--qb-accent) !important;
            color: {{ $branding['on_primary'] }} !important;
        }
        .qb-status-verified { border-left: 4px solid #50C878 !important; }
        .qb-status-pending { border-left: 4px solid #FFC107 !important; }
        .qb-status-cancelled { border-left: 4px solid #F44336 !important; }
        .qb-icon { color: var(--qb-primary); }
        .qb-icon-bg { background: rgba(var(--qb-primary-rgb), 0.12); }
    </style>
    @livewireStyles
    @yield('styles')
</head>
<body id="kt_body" class="app-blank" data-bs-theme="light">
<script>
    document.documentElement.setAttribute('data-theme', 'light');
    document.documentElement.setAttribute('data-bs-theme', 'light');
</script>

<div class="d-flex flex-column flex-root" id="kt_app_root">
    <!--begin::Header-->
    <div class="qb-customer-header shadow-sm">
        <div class="container d-flex align-items-center justify-content-between py-4">
            <a href="{{ route('customer.dashboard') }}" class="d-flex align-items-center text-white fw-bolder fs-3 text-decoration-none">
                @if (! empty($branding['logo_url']))
                    <img src="{{ $branding['logo_url'] }}" alt="{{ $brandName }}" style="max-height: 36px; max-width: 160px;">
                @else
                    {{ $brandName }}
                @endif
            </a>

            <div class="d-flex align-items-center gap-4">
                @auth('customer')
                    <a href="/shop" class="btn btn-sm fw-semibold" style="background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.3);">Shop</a>
                    @livewire('ecommerce.cart-icon')
                    <div class="dropdown">
                        <button class="btn btn-sm fw-semibold dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.25);">
                            <i class="ki-duotone ki-profile-circle fs-4 me-1 text-white"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            {{ Auth::guard('customer')->user()->name }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" style="border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.12);">
                            <li><a class="dropdown-item" href="{{ route('customer.dashboard') }}"><i class="ki-duotone ki-home fs-5 me-2 qb-icon"><span class="path1"></span><span class="path2"></span></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="{{ route('customer.orders') }}"><i class="ki-duotone ki-parcel fs-5 me-2 qb-icon"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>My Orders</a></li>
                            <li><a class="dropdown-item" href="{{ route('customer.profile.edit') }}"><i class="ki-duotone ki-user fs-5 me-2 qb-icon"><span class="path1"></span><span class="path2"></span></i>My Profile</a></li>
                            <li><a class="dropdown-item" href="{{ route('customer.password.edit') }}"><i class="ki-duotone ki-lock fs-5 me-2 qb-icon"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('customer.logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger"><i class="ki-duotone ki-exit-right fs-5 me-2"><span class="path1"></span><span class="path2"></span></i>Logout</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @else
                    <a href="{{ route('customer.login') }}" class="btn btn-sm fw-semibold" style="background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.3);">Login</a>
                    <a href="{{ route('customer.register') }}" class="btn btn-sm fw-semibold" style="background: #fff; color: var(--qb-primary);">Register</a>
                @endauth
            </div>
        </div>
    </div>
    <!--end::Header-->

    <!--begin::Content-->
    <div class="container py-10">
        @if(session('success'))
            <div class="alert alert-success d-flex align-items-center mb-5">
                <i class="ki-duotone ki-shield-tick fs-2hx text-success me-3"><span class="path1"></span><span class="path2"></span></i>
                <div>{{ session('success') }}</div>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger d-flex align-items-center mb-5">
                <i class="ki-duotone ki-shield-cross fs-2hx text-danger me-3"><span class="path1"></span><span class="path2"></span></i>
                <div>{{ session('error') }}</div>
            </div>
        @endif

        @yield('content')
    </div>
    <!--end::Content-->
</div>

<script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
<script src="{{ asset('assets/js/scripts.bundle.js') }}"></script>
@livewireScripts
@yield('scripts')
</body>
</html>
