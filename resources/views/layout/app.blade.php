<!DOCTYPE html>
<html lang="en">
<!--begin::Head-->
<head>
    <base href=""/>
    <title>APEX POS @yield('header')</title>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" href="{{asset('assets/media/logos/favicon.ico')}}" />
    <!--begin::Fonts(mandatory for all pages)-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700"/>
    <!--end::Fonts-->
    <!--begin::Global Stylesheets Bundle(mandatory for all pages)-->
    <link href="{{ asset("assets/plugins/global/plugins.bundle.css")}} " rel="stylesheet" type="text/css"/>
    <link href="{{ asset("assets/css/style.bundle.css")}} " rel="stylesheet" type="text/css"/>
    <!--end::Global Stylesheets Bundle-->
    {{-- Tenant branding is intentionally NOT applied to /admin. The
         backoffice always renders in Apex defaults. Branding (palette
         + logo + brand_name) only reaches /shop and customer-facing
         surfaces (auth pages, customer portal, transactional email). --}}
    <!--begin::Vendor Stylesheets(used for this page only)-->
    @yield('vendor-styles')
    <!--end::Vendor Stylesheets-->
    @yield('styles')
    <script>
        // Frame-busting to prevent site from being loaded within a frame without permission (click-jacking)
        if (window.top != window.self) {
            window.top.location.replace(window.self.location.href);
        }
    </script>
    @livewireStyles
</head>
<!--end::Head-->
<!--begin::Body-->
<body id="kt_app_body" data-kt-app-header-fixed="true" data-kt-app-header-fixed-mobile="true"
      data-kt-app-sidebar-enabled="true" data-kt-app-sidebar-fixed="true" data-kt-app-sidebar-push-toolbar="true"
      data-kt-app-sidebar-push-footer="true" class="app-default">
<!--layout-partial:partials/theme-mode/_init.html-->
    @include('layout.partials.theme-mode._init')
<!--layout-partial:layout/_default.html-->
    @include('layout.layout._default')
<!--layout-partial:partials/_scrolltop.html-->
    @include('layout.partials._scrolltop')
<!--begin::Modals-->
    @yield('modals')
<!--end::Modals-->
<!--begin::Javascript-->
    <!--begin::Global Javascript Bundle(mandatory for all pages)-->
    <script src="{{ asset("assets/plugins/global/plugins.bundle.js") }} "></script>
    <script src="{{ asset("assets/js/scripts.bundle.js") }} "></script>
    <script src="{{ asset("assets/js/helpers.js") }}"></script>
    <script type="text/javascript">
        $(document).ready(function(){
            $('body').tooltip({selector: '[data-bs-toggle="tooltip"]'});
        })
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
        });
    </script>
    <!--end::Global Javascript Bundle-->
    @yield('vendor-scripts')
    @yield('scripts')
    @yield('add-scripts')
    @livewireScripts
<!--end::Javascript-->
</body>
<!--end::Body-->
</html>
