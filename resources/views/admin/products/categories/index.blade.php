@extends('layout.app')
@section('header')
    - Categories
@endsection
@section('title')
    Categories
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3 text-muted">Categories</li>
@endsection
@section('actions')
    <x-data-table.actions></x-data-table.actions>
    <x-general.search-table
        title="Category"
    ></x-general.search-table>
    @if (auth()->user()->role->itms_create)
        <x-modals.create-button
                identifier="category"
        ></x-modals.create-button>
    @endif
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
            identifier="category"
            title="Category"
    >
        <div class="form-group fv-row mb-6">
            <label for="name" class="form-label required">Category Name</label>
            <input type="text" class="form-control" id="name" name="name"/>
        </div>
        <div class="form-group fv-row mb-6">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3" maxlength="1000" placeholder="Brief description of this category..."></textarea>
        </div>
        <div class="form-group fv-row mb-6">
            <label for="icon" class="form-label">Icon (Emoji or Text)</label>
            <input type="text" class="form-control" id="icon" name="icon" maxlength="100" placeholder="e.g. 🥩 or 🛒"/>
            <div class="form-text text-muted">Enter an emoji or short text to display as the category icon on the shop page</div>
        </div>
        <div class="form-group fv-row mb-6">
            <label class="form-label">Category Image</label>
            <div class="image-input image-input-outline" data-kt-image-input="true" id="categoryImageInput"
                 style="background-image: url({{ asset('/assets/media/svg/shapes/abstract-4.svg') }})">
                <div class="image-input-wrapper w-150px h-150px" id="categoryImagePreview"
                     style="background-image: url({{ asset('/assets/media/svg/shapes/abstract-4-dark.svg') }})"></div>
                <label class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
                       data-kt-image-input-action="change" data-bs-toggle="tooltip" title="Change image">
                    <i class="fa-solid fa-pencil"></i>
                    <input type="file" name="image" accept=".png,.jpg,.jpeg"/>
                    <input type="hidden" name="old_image" value=""/>
                </label>
                <span class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
                      data-kt-image-input-action="cancel" data-bs-toggle="tooltip" title="Cancel">
                    <i class="fa-solid fa-xmark"></i>
                </span>
                <span class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
                      data-kt-image-input-action="remove" data-bs-toggle="tooltip" title="Remove">
                    <i class="fa-solid fa-trash"></i>
                </span>
            </div>
        </div>
    </x-modals.create-edit>
    <x-modals.delete
            identifier="category"
            title-identifier="Category"
    ></x-modals.delete>
@endsection
@section('vendor-styles')
    <link rel="stylesheet" href="{{asset("assets/plugins/custom/datatables/datatables.bundle.css")}}">
@endsection
@section('vendor-scripts')
    {{-- DataTables --}}
    <script src="{{asset("assets/plugins/custom/datatables/datatables.bundle.js")}}"></script>
@endsection
@section('scripts')
    
    <script src="{{ asset('assets/js/pages/categories/index.js') }}"></script>
@endsection
