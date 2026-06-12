@props([
    'showExport' => true
])

<!--begin::Menu-->
<div class="app-navbar-item">
    <!--begin::Menu-->
    <button type="button" class="btn btn-icon btn-color-primary btn-active-light-primary"
            data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
        <!--begin::Svg Icon | path: icons/duotune/general/gen024.svg-->
        <i class="ki-duotone ki-abstract-14 fs-1 m-1">
            <span class="path1"></span>
            <span class="path2"></span>
        </i>
        <!--end::Svg Icon-->
    </button>
    <!--begin::Menu 1-->
    <div id="datatables_menu"
         class="menu menu-sub menu-sub-dropdown menu-column menu-rounded w-250px w-md-300px menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-200px py-4"
         data-kt-menu="true" id="card_actions" style="">
        {{ $slot }}
        @if($showExport)
            <!--begin::Header-->
            <div class="px-5 py-3">
                <div class="fs-5 text-dark fw-bold">Export Options</div>
            </div>
            <!--end::Header-->
            <!--begin::Menu item-->
            <div class="menu-item px-3">
                <a href="#" class="menu-link px-3" data-kt-export="copy">
                    Copy to clipboard
                </a>
            </div>
            <!--end::Menu item-->
            <!--begin::Menu item-->
            <div class="menu-item px-3">
                <a href="#" class="menu-link px-3" data-kt-export="excel">
                    Export as Excel
                </a>
            </div>
            <!--end::Menu item-->
            <!--begin::Menu item-->
            <div class="menu-item px-3">
                <a href="#" class="menu-link px-3" data-kt-export="csv">
                    Export as CSV
                </a>
            </div>
            <!--end::Menu item-->
            <!--begin::Menu item-->
            <div class="menu-item px-3">
                <a href="#" class="menu-link px-3" data-kt-export="pdf">
                    Export as PDF
                </a>
            </div>
            <!--end::Menu item-->
            <!--begin::Hide default export buttons-->
            <div id="datatable_buttons" class="d-none"></div>
            <!--end::Hide default export buttons-->
        @endif
    </div>
    <!--end::Menu 1-->
</div>