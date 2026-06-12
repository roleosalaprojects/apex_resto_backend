<!DOCTYPE html>
<html lang="en" data-bs-theme-mode="light">
	<!--begin::Head-->
	<head>
		<base href="" />
		<title>@yield('header')</title>
		<meta charset="utf-8" />
		<meta property="og:locale" content="en_PH" />
		<link rel="canonical" href="https://preview.keenthemes.com/metronic8" />
		<link rel="shortcut icon" href="assets/media/logos/favicon.ico" />
		<!--begin::Fonts(mandatory for all pages)-->
		<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
		<!--end::Fonts-->
		<!--begin::Global Stylesheets Bundle(mandatory for all pages)-->
        <link href="{{ asset("assets/plugins/global/plugins.bundle.css")}} " rel="stylesheet" type="text/css"/>
        <link href="{{ asset("assets/css/style.bundle.css")}} " rel="stylesheet" type="text/css"/>
		<!--end::Global Stylesheets Bundle-->
		@yield('styles')
	</head>
	<!--end::Head-->
	<!--begin::Body-->
	<body id="kt_app_body" class="bg-white">
		<!--begin::App-->
        <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
            <!--begin::Page-->
            <div class="app-page flex-column flex-column-fluid" id="kt_app_page">
                <!--begin::Wrapper-->
                <div class="app-wrapper flex-column flex-row-fluid" id="kt_app_wrapper">
                    <div class="app-main flex-column flex-row-fluid" id="kt_app_main">
                        <!--begin::Content wrapper-->
                        <div class="d-flex flex-column flex-column-fluid">
                            <!--begin::Content-->
                            <div id="kt_app_content" class="app-content flex-column-fluid">
                                <!--begin::Content container-->
                                <div id="kt_app_content_container" class="app-container container-fluid">
                                    @yield('content')
                                </div>
                                <!--end::Content container-->
                            </div>
                            <!--end::Content-->
                        </div>
                        <!--end::Content wrapper-->
                    </div>
                    <!--end:::Main-->
                </div>
                <!--end::Wrapper-->
            </div>
            <!--end::Page-->
        </div>
        <!--end::App-->
		<!--begin::Javascript-->
		<!--begin::Global Javascript Bundle(mandatory for all pages)-->
        <script src="{{asset("assets/plugins/global/plugins.bundle.js")}}"></script>
        <script src="{{asset("assets/js/scripts.bundle.js")}}"></script>
		<!--end::Global Javascript Bundle-->
		@yield('scripts')
	</body>
	<!--end::Body-->
</html>