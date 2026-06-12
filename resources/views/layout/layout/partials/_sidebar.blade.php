<!--begin::Sidebar-->
<div id="kt_app_sidebar" class="app-sidebar "
     data-kt-drawer="true" data-kt-drawer-name="app-sidebar" data-kt-drawer-activate="{default: true, lg: false}"
     data-kt-drawer-overlay="true" data-kt-drawer-width="auto" data-kt-drawer-direction="start"
     data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle"
>
    <!--layout-partial:layout/partials/sidebar/_menu.html-->
    @include('layout.layout.partials.sidebar._menu')
    <!--layout-partial:layout/partials/sidebar/_footer.html-->
    @include('layout.layout.partials.sidebar._footer')
</div>
<!--end::Sidebar-->