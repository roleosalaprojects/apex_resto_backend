@extends('layout.app')
@section('header')
    - GCash
@endsection
@section('title')
    GCash
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3 text-muted">GCash</li>
@endsection
@section('actions')
    <x-general.search-table
            title="Category"
    ></x-general.search-table>
    @if (auth()->user()->role->itms_create)
        <x-modals.create-button
                identifier="category"
        ></x-modals.create-button>
    @endif
    <!--begin::Menu-->
    <button type="button" class="btn btn-sm btn-icon btn-color-primary btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
        <!--begin::Svg Icon | path: icons/duotune/general/gen024.svg-->
        <span class="svg-icon svg-icon-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24">
                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                    <rect x="5" y="5" width="5" height="5" rx="1" fill="currentColor"></rect>
                    <rect x="14" y="5" width="5" height="5" rx="1" fill="currentColor" opacity="0.3"></rect>
                    <rect x="5" y="14" width="5" height="5" rx="1" fill="currentColor" opacity="0.3"></rect>
                    <rect x="14" y="14" width="5" height="5" rx="1" fill="currentColor" opacity="0.3"></rect>
                </g>
            </svg>
        </span>
        <!--end::Svg Icon-->
    </button>
    <!--begin::Menu 1-->
    <div id="datatables_menu" class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-200px py-4" data-kt-menu="true" id="card_actions" style="">
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
    </div>
    <!--end::Menu 1-->
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <x-general.data-table
                            table-id="categoryTable">
                        <th>Name</th>
                        <th></th>
                    </x-general.data-table>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('modals')
    <x-modals.create-edit
            identifier="gcash"
            title="GCash"
    >
        <div class="form-group fv-row mb-6">
            <label for="name" class="form-label required">GCash Name</label>
            <input type="text" class="form-control" id="name" name="name"/>
        </div>
    </x-modals.create-edit>
    <x-modals.delete
            identifier="gcash"
            title-identifier="GCash"
    ></x-modals.delete>
@endsection
@section('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}">
@endsection
@section('vendor-scripts')
    <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
@endsection
@section('scripts')
    
    <script src="{{ asset('assets/js/pages/gcashes/index.js') }}"></script>
@endsection