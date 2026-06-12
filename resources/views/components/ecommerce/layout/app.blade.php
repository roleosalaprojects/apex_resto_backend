@props([
    // Pages can override any of these to customize the link preview
    // they generate when shared on Messenger / iMessage / Twitter / etc.
    // Defaults below pull from the active tenant's branding so a
    // freshly-cloned tenant gets a sensible card without code changes.
    'ogTitle' => null,
    'ogDescription' => null,
    'ogImage' => null,
    'ogType' => 'website',
])
@php
    $brandName = $branding['brand_name'] ?? 'Quick Baskets';
    $hexToRgb = function (string $hex): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return preg_match('/^[0-9a-f]{6}$/i', $hex) === 1
            ? implode(', ', sscanf($hex, '%02x%02x%02x'))
            : '24, 88, 253';
    };
    $primaryRgb = $hexToRgb($branding['primary']);
    $secondaryRgb = $hexToRgb($branding['secondary']);
    $accentRgb = $hexToRgb($branding['accent']);

    // Open Graph defaults. Concrete values matter: without an absolute
    // image URL, Messenger renders just a bare link instead of a card.
    $pageTitle = $ogTitle ?? ($brandName.' - Your one stop shop for your family\'s needs.');
    $pageDescription = $ogDescription ?? ($branding['tagline'] ?? 'Shop fresh groceries, household essentials, and more — delivered or ready for pickup.');
    // Final fallback is the tenant's favicon — every install has one,
    // and Messenger renders even a small square image rather than no
    // image at all. Ideal flow is for the tenant to upload a proper
    // 1200x630 hero via Branding settings, then logo_url wins.
    $pageImage = $ogImage
        ?? (! empty($branding['logo_url']) ? (str_starts_with($branding['logo_url'], 'http') ? $branding['logo_url'] : asset(ltrim($branding['logo_url'], '/'))) : asset('assets/media/logos/favicon.ico'));
    $pageUrl = request()->url();
@endphp
<!DOCTYPE html>
<html lang="en">
<!--begin::Head-->
<head><base href=""/>
    <title>{{ $pageTitle }}</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="{{ $pageDescription }}" />

    {{-- Open Graph (Facebook / Messenger / iMessage / WhatsApp / Slack) --}}
    <meta property="og:locale" content="en_PH" />
    <meta property="og:type" content="{{ $ogType }}" />
    <meta property="og:site_name" content="{{ $brandName }}" />
    <meta property="og:title" content="{{ $pageTitle }}" />
    <meta property="og:description" content="{{ $pageDescription }}" />
    <meta property="og:url" content="{{ $pageUrl }}" />
    <meta property="og:image" content="{{ $pageImage }}" />
    <meta property="og:image:alt" content="{{ $brandName }}" />

    {{-- Twitter / X mirror — falls back to OG on most clients but
         Twitter itself reads these specifically. `summary_large_image`
         gives the wide preview card. --}}
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $pageTitle }}" />
    <meta name="twitter:description" content="{{ $pageDescription }}" />
    <meta name="twitter:image" content="{{ $pageImage }}" />

    <link rel="canonical" href="{{ $pageUrl }}" />
    <link rel="shortcut icon" href="{{asset('assets/media/logos/favicon.ico')}}" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <link href="{{asset('assets/plugins/global/plugins.bundle.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('assets/css/style.bundle.css')}}" rel="stylesheet" type="text/css" />
    <style>
        :root {
            --qb-primary: #FF8C69;
            --qb-primary-dark: #D9684A;
            --qb-accent: #E85D3A;
            --qb-bg: #FFF8F5;
        }
        body.qb-body { background-color: var(--qb-bg) !important; }
        .qb-header {
            background: linear-gradient(135deg, var(--qb-primary), var(--qb-primary-dark));
            padding: 0;
        }
        .qb-header .menu-link, .qb-header .menu-link.nav-link {
            color: rgba(255,255,255,0.85) !important;
        }
        .qb-header .menu-link:hover, .qb-header .menu-link.active {
            color: #fff !important;
        }
        .qb-btn-primary {
            background-color: var(--qb-primary) !important;
            border-color: var(--qb-primary) !important;
            color: #fff !important;
        }
        .qb-btn-primary:hover {
            background-color: var(--qb-primary-dark) !important;
            border-color: var(--qb-primary-dark) !important;
        }
        .qb-btn-accent {
            background-color: var(--qb-accent) !important;
            border-color: var(--qb-accent) !important;
            color: #fff !important;
        }
        .qb-price { color: var(--qb-accent); font-weight: 700; }
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
        .qb-badge-cart {
            background-color: var(--qb-accent) !important;
            color: #fff !important;
        }
        .qb-category-card {
            border-left: 4px solid var(--qb-primary) !important;
            border-radius: 10px !important;
        }
        .qb-status-verified { border-left: 4px solid #50C878 !important; }
        .qb-status-pending { border-left: 4px solid #FFC107 !important; }
        .qb-status-cancelled { border-left: 4px solid #F44336 !important; }
        .qb-icon { color: var(--qb-primary); }
        .qb-icon-bg { background: rgba(255,140,105,0.12); }
        /* Branded fallback tile for missing product images. */
        .qb-img-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 28% 24%, rgba(var(--qb-primary-rgb, 255, 140, 105), 0.20), transparent 58%),
                radial-gradient(circle at 78% 78%, rgba(var(--qb-accent-rgb, 232, 93, 58), 0.14), transparent 52%),
                #f7f7f9;
        }
        .qb-img-placeholder span {
            line-height: 1;
            filter: drop-shadow(0 4px 10px rgba(0,0,0,0.12));
        }
        /* Category dropdown styles */
        .category-item:hover {
            background: rgba(255,140,105,0.15) !important;
            transform: translateY(-1px);
        }
        .category-item.active {
            background: var(--qb-primary) !important;
        }
        .category-item.active span {
            color: #fff !important;
        }
    </style>
    @isset($branding)
        {{-- Tenant branding override: hex values are validated by
             BrandingService::sanitizeHex(), safe to inline. The *-rgb
             variants are for use inside rgba() (e.g. translucent shadows
             and tints) that can't take a hex directly. --}}
        <style>
            :root {
                --qb-primary: {{ $branding['primary'] }};
                --qb-primary-dark: {{ $branding['secondary'] }};
                --qb-accent: {{ $branding['accent'] }};
                --qb-primary-rgb: {{ $primaryRgb }};
                --qb-primary-dark-rgb: {{ $secondaryRgb }};
                --qb-accent-rgb: {{ $accentRgb }};
                --bs-primary: {{ $branding['primary'] }};
                --bs-primary-active: {{ $branding['secondary'] }};
                --bs-info: {{ $branding['accent'] }};
            }
        </style>
    @endisset
    @livewireStyles
    {{$styles}}
</head>
<!--end::Head-->
<!--begin::Body-->
<body id="kt_body" data-bs-spy="scroll" data-bs-target="#kt_landing_menu" class="qb-body position-relative app-blank">
{{-- /shop is light-mode only. The Metronic theme script (admin layout)
     persists `data-theme` to localStorage, which is shared across all
     routes on the same origin — without this override, an admin who
     toggles dark mode also flips the shop dark with no toggle to undo
     it. We hardcode light here and never read localStorage. --}}
<script>
    document.documentElement.setAttribute('data-theme', 'light');
    document.documentElement.setAttribute('data-bs-theme', 'light');
</script>
<!--begin::Root-->
<div class="d-flex flex-column flex-root" id="kt_app_root">
    <!--begin::Header Section-->
    <div class="mb-0" id="home">
        <!--begin::Header-->
        <div class="qb-header" data-kt-sticky="true" data-kt-sticky-name="landing-header" data-kt-sticky-offset="{default: '200px', lg: '200px'}">
            <div class="container">
                <div class="d-flex align-items-center justify-content-between" style="min-height: 70px;">
                    <!--begin::Logo-->
                    <div class="d-flex align-items-center flex-equal">
                        <button class="btn btn-icon me-3 d-flex d-lg-none" id="kt_landing_menu_toggle">
                            <span class="svg-icon svg-icon-2hx">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M21 7H3C2.4 7 2 6.6 2 6V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V6C22 6.6 21.6 7 21 7Z" fill="#ffffff" />
                                    <path opacity="0.7" d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z" fill="#ffffff" />
                                </svg>
                            </span>
                        </button>
                        <a href="{{ route('shops.') }}" class="text-decoration-none d-flex align-items-center">
                            @if (! empty($branding['logo_url']))
                                <img src="{{ $branding['logo_url'] }}" alt="{{ $brandName }}" style="max-height: 40px; max-width: 160px;">
                            @else
                                <span class="fs-1 fw-bold text-white">{{ $brandName }}</span>
                            @endif
                        </a>
                    </div>
                    <!--end::Logo-->
                    <!--begin::Menu wrapper-->
                    <div class="d-lg-block" id="kt_header_nav_wrapper">
                        <div class="d-lg-block p-5 p-lg-0" data-kt-drawer="true" data-kt-drawer-name="landing-menu" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="200px" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_landing_menu_toggle" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_body', lg: '#kt_header_nav_wrapper'}">
                            <div class="menu menu-column flex-nowrap menu-rounded menu-lg-row menu-title-gray-500 menu-state-title-primary nav nav-flush fs-5 fw-semibold" id="kt_landing_menu">
                                <div class="menu-item">
                                    <a class="menu-link nav-link @if(Route::current()->getName() == 'shops.') active @endif py-3 px-4 px-xxl-6" href="{{ route('shops.') }}" data-kt-scroll-toggle="true" data-kt-drawer-dismiss="true">
                                        <i class="ki-duotone ki-home fs-4 me-1 text-white"><span class="path1"></span><span class="path2"></span></i>
                                        Home
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link nav-link @if(Route::current()->getName() == 'shops.products.index' && !request('category')) active @endif py-3 px-4 px-xxl-6" href="{{ route('shops.products.index') }}" data-kt-scroll-toggle="true" data-kt-drawer-dismiss="true">
                                        <i class="ki-duotone ki-shop fs-4 me-1 text-white"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                                        Products
                                    </a>
                                </div>
                                @if(isset($navCategories) && $navCategories->count() > 0)
                                <!--begin::Desktop Dropdown-->
                                <div class="menu-item dropdown d-none d-lg-block">
                                    <a class="menu-link nav-link py-3 px-4 px-xxl-6 dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="ki-duotone ki-category fs-4 me-1 text-white"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                        Categories
                                    </a>
                                    <div class="dropdown-menu p-4" style="min-width: 320px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15);">
                                        <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                                            <i class="ki-duotone ki-category fs-2 me-2 qb-icon"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                            <span class="fw-bold fs-5">Browse Categories</span>
                                        </div>
                                        <div class="row g-2" style="max-height: 350px; overflow-y: auto;">
                                            @foreach($navCategories as $cat)
                                            <div class="col-6">
                                                <a class="d-flex align-items-center p-3 rounded-2 text-decoration-none category-item @if(request('category') == $cat->id) active @endif" href="{{ route('shops.products.index', ['category' => $cat->id]) }}" style="background: #f8f9fa; transition: all 0.2s ease;">
                                                    <span class="fs-4 me-2">{{ $cat->icon ?: '📦' }}</span>
                                                    <span class="fw-semibold text-gray-800 fs-7">{{ ucwords(strtolower($cat->name)) }}</span>
                                                </a>
                                            </div>
                                            @endforeach
                                        </div>
                                        <div class="mt-3 pt-2 border-top">
                                            <a href="{{ route('shops.products.index') }}" class="btn btn-sm w-100" style="background: var(--qb-primary); color: #fff;">
                                                <i class="ki-duotone ki-eye fs-5 me-1 text-white"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                                View All Products
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <!--end::Desktop Dropdown-->
                                <!--begin::Mobile Accordion-->
                                <div class="menu-item d-lg-none">
                                    <a class="menu-link nav-link py-3 px-4" data-bs-toggle="collapse" href="#mobileCategoriesCollapse" role="button" aria-expanded="false" aria-controls="mobileCategoriesCollapse">
                                        <i class="ki-duotone ki-category fs-4 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                        Categories
                                        <i class="ki-duotone ki-down fs-6 ms-auto"><span class="path1"></span><span class="path2"></span></i>
                                    </a>
                                    <div class="collapse" id="mobileCategoriesCollapse">
                                        <div class="ps-6 py-2" style="max-height: 300px; overflow-y: auto;">
                                            @foreach($navCategories as $cat)
                                            <a class="d-flex align-items-center py-2 px-3 text-decoration-none text-gray-800 @if(request('category') == $cat->id) fw-bold @endif" href="{{ route('shops.products.index', ['category' => $cat->id]) }}" data-kt-drawer-dismiss="true">
                                                <span class="me-2">{{ $cat->icon ?: '📦' }}</span>
                                                {{ ucwords(strtolower($cat->name)) }}
                                            </a>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <!--end::Mobile Accordion-->
                                @endif
                            </div>
                        </div>
                    </div>
                    <!--end::Menu wrapper-->
                    <!--begin::Toolbar-->
                    <div class="flex-equal text-end ms-1">
                        @if (Route::has('customer.login'))
                            <div class="top-right links d-flex align-items-center justify-content-end gap-3">
                                @auth('customer')
                                    <livewire:ecommerce.cart-icon />
                                    <div class="dropdown">
                                        <button class="btn btn-sm fw-bold dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.25);">
                                            <i class="ki-duotone ki-profile-circle fs-4 me-1 text-white"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                            {{ Auth::guard('customer')->user()->name }}
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" style="border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.12);">
                                            <li><a class="dropdown-item" href="{{ route('customer.dashboard') }}"><i class="ki-duotone ki-home fs-5 me-2 qb-icon"><span class="path1"></span><span class="path2"></span></i>Dashboard</a></li>
                                            <li><a class="dropdown-item" href="{{ route('customer.orders') }}"><i class="ki-duotone ki-parcel fs-5 me-2 qb-icon"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>My Orders</a></li>
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
                                    <a href="{{ route('customer.login') }}" class="btn btn-sm fw-bold" style="background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.3);">Sign In</a>
                                @endauth
                            </div>
                        @endif
                    </div>
                    <!--end::Toolbar-->
                </div>
            </div>
        </div>
        <!--end::Header-->
    </div>
    <!--end::Header Section-->
    <!--begin::Main Section-->
    <div class="pt-10">
        {{ $slot }}
    </div>
    <!--end::Main Section-->
    <!--begin::Scrolltop-->
    <div id="kt_scrolltop" class="scrolltop" data-kt-scrolltop="true">
        <span class="svg-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect opacity="0.5" x="13" y="6" width="13" height="2" rx="1" transform="rotate(90 13 6)" fill="currentColor" />
                <path d="M12.5657 8.56569L16.75 12.75C17.1642 13.1642 17.8358 13.1642 18.25 12.75C18.6642 12.3358 18.6642 11.6642 18.25 11.25L12.7071 5.70711C12.3166 5.31658 11.6834 5.31658 11.2929 5.70711L5.75 11.25C5.33579 11.6642 5.33579 12.3358 5.75 12.75C6.16421 13.1642 6.83579 13.1642 7.25 12.75L11.4343 8.56569C11.7467 8.25327 12.2533 8.25327 12.5657 8.56569Z" fill="currentColor" />
            </svg>
        </span>
    </div>
    <!--end::Scrolltop-->
</div>
<!--end::Root-->
<!--begin::Javascript-->
<script src="{{asset('assets/plugins/global/plugins.bundle.js')}}"></script>
<script src="{{asset('assets/js/scripts.bundle.js')}}"></script>
<script src="{{asset('assets/js/custom/landing.js')}}"></script>
{{ $scripts }}
@livewireScripts
<!--end::Javascript-->
</body>
<!--end::Body-->
</html>
