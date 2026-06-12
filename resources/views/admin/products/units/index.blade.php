@extends('layout.app')
@section('header')
    Unit of Measure
@endsection
@section('title')
    Unit of Measure
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item text-muted">UoM</li>
@endsection
@section('actions')
    <x-data-table.actions></x-data-table.actions>
    <x-general.search-table title="Unit"></x-general.search-table>
    @if (auth()->user()->role->itms_create)
        <x-modals.create-button
            identifier="unit"
        ></x-modals.create-button>
    @endif
@endsection
@section('content')
    <div class="card">
        <div class="card-body">
            <x-data-table.table
                table-id="unitTable"
            >
                <th>Name</th>
                <th></th>
            </x-data-table.table>
        </div>
    </div>
    {{-- Modal for Deletion --}}
    <div class="modal fade" tabindex="-1" id="deleteModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title">Delete Unit</h5>

                    <!--begin::Close-->
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                        <span class="svg-icon svg-icon-2x"></span>
                    </div>
                    <!--end::Close-->
                </div>

                <div class="modal-body">
                    <h5 id="unit_name">Name Here</h5>
                    <label class="form-label">Are you sure you want to delete this Unit?</label>
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
            identifier="unit"
            title="Unit"
    >
        <div class="form-group fv-row mb-6">
            <label for="name" class="form-label required">Unit Name</label>
            <input type="text" class="form-control" id="name" name="name"/>
        </div>
    </x-modals.create-edit>
    <x-modals.delete
            identifier="unit"
            title-identifier="Unit"
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
    
    <script src="{{ asset('assets/js/pages/units/index.js') }}"></script>
@endsection
