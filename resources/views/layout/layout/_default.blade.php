<!--begin::App-->
<div class="d-flex flex-column flex-root app-root" id="kt_app_root">
    <!--begin::Page-->
    <div class="app-page  flex-column flex-column-fluid " id="kt_app_page">
        <!--layout-partial:layout/partials/_header.html-->
        @include('layout.layout.partials._header')
        <!--begin::Wrapper-->
        <div class="app-wrapper  flex-column flex-row-fluid " id="kt_app_wrapper">
            <!--layout-partial:layout/partials/_sidebar.html-->
            @include('layout.layout.partials._sidebar')
            <!--begin::Main-->
            <div class="app-main flex-column flex-row-fluid " id="kt_app_main">
                <!--begin::Content wrapper-->
                <div class="d-flex flex-column flex-column-fluid">
                    <!--layout-partial:layout/partials/_content.html-->
                    <!--begin::Content-->
                    <div id="kt_app_content" class="app-content  flex-column-fluid ">
                        <!--begin::Content container-->
                        <div id="kt_app_content_container" class="app-container container  container-fluid">
                            @include('layout.messages')
                            @yield('content')
                        </div>
                        <!--end::Content container-->
                    </div>
                    <!--end::Content-->
                </div>
                <!--end::Content wrapper-->
                <!--layout-partial:layout/partials/_footer.html-->
                @include('layout.layout.partials._footer')
            </div>
            <!--end:::Main-->
        </div>
        <!--end::Wrapper-->
    </div>
    <!--end::Page-->
</div>
<!--end::App-->
<!--layout-partial:partials/_drawers.html-->
