@extends('layout.app')
@section('header')
    - Suppliers
@endsection
@section('title')
Suppliers
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">Suppliers</li>
@endsection
@section('actions')
    <x-data-table.actions></x-data-table.actions>
    <x-general.search-table title="Supplier"></x-general.search-table>
    @if ($access->spplrs_create)
        <x-modals.create-button identifier="supplier"></x-modals.create-button>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <x-data-table.table
                        table-id="supplierTable"
                    >
                        <th>Name</th>
                        <th>TIN</th>
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th></th>
                    </x-data-table.table>
                </div>
            </div>
        </div>
    </div>
{{-- Modal for Deletion --}}
<div class="modal fade" tabindex="-1" id="deleteModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">Delete Supplier</h5>

                <!--begin::Close-->
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <span class="svg-icon svg-icon-2x"></span>
                </div>
                <!--end::Close-->
            </div>

            <div class="modal-body">
                <h5 id="supplier_name">Name Here</h5>
                <label class="form-label">Are you sure you want to delete this Supplier?</label>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="submit" id="confirm_delete" class="btn btn-danger font-weight-bold" form="">Delete</button>
            </div>
        </div>
    </div>
</div>
@endsection
@section('modals')
    <x-modals.create-edit
            identifier="supplier"
            title="Suppliers"
    >
        <div class="form-group fv-row mb-6">
            <label for="name" class="form-label required">Supplier Name</label>
            <input type="text" class="form-control" id="name" name="name"/>
        </div>
        <div class="form-group fv-row mb-6">
            <label for="tin" class="form-label">TIN</label>
            <input type="text" class="form-control" id="tin" name="tin"/>
        </div>
        <div class="form-group fv-row mb-6">
            <label for="contact" class="form-label required">Contact Person</label>
            <input type="text" class="form-control" id="contact" name="contact"/>
        </div>
        <div class="form-group fv-row mb-6">
            <label for="email" class="form-label">Email Address</label>
            <input type="text" class="form-control" id="email" name="email"/>
        </div>
        <div class="form-group fv-row mb-6">
            <label for="address" class="form-label required">Address</label>
            <input type="text" class="form-control" id="address" name="address"/>
        </div>
        <div class="form-group fv-row mb-6">
            <label for="city" class="form-label required">City</label>
            <input type="text" class="form-control" id="city" name="city"/>
        </div>
        <div class="form-group fv-row mb-6">
            <label for="zip" class="form-label required">ZIP Code</label>
            <input type="text" class="form-control" id="zip" name="zip"/>
        </div>
        <div class="form-group fv-row mb-6">
            <label for="province" class="form-label required">Province</label>
            <input type="text" class="form-control" id="province" name="province"/>
        </div>
        <div class="form-group fv-row mb-6">
            <label for="country" class="form-label">Country</label>
            <input type="text" class="form-control" id="country" name="country"/>
        </div>
        <div class="form-group fv-row mb-6">
            <label for="note" class="form-label">Note</label>
            <input type="text" class="form-control" id="note" name="note"/>
        </div>
    </x-modals.create-edit>
    <x-modals.delete
        identifier="supplier"
        title-identifier="Supplier"
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
    
    <script src="{{ asset('assets/js/pages/suppliers/index.js') }}"></script>
@endsection
