@extends('layout.app')
@section('header')
    - Special Customers
@endsection
@section('title')
    Special Customers
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">Special Customers</li>
@endsection
@section('actions')
    <x-general.search-table
        title="Customer"
    ></x-general.search-table>
    <x-modals.create-button
            identifier="customer"
    ></x-modals.create-button>
@endsection
@section('content')
    <div class="card">
        <div class="card-body">
            <x-data-table.table
                table-id="customerTable"
            >
                <th>Name</th>
                <th>ID #</th>
                <th>Type</th>
                <th>TIN</th>
                <th></th>
            </x-data-table.table>
        </div>
    </div>
@endsection
@section('modals')
    <x-modals.create-edit
        identifier="customer"
        title="Customer"
    >
        <div class="form-group fv-row mb-6">
            <label for="name" class="form-label required">Customer Name</label>
            <input type="text" class="form-control" id="name" name="name"/>
        </div>
        <div class="form-group fv-row mb-6">
            <label for="identifier" class="form-label required">Identification</label>
            <input type="text" class="form-control" id="identifier" name="identifier"/>
        </div>
        <div class="form-group fv-row mb-6">
            <label for="tin" class="form-label required">Tax Identification Number (TIN)</label>
            <input type="text" class="form-control" id="tin" name="tin"/>
        </div>
        <div class="form-group fv-row mb-6">
            <label for="type" class="form-label required">Special Customer Type</label>
            <select name="type" id="type" class="form-select">
                <option value="0">Senior Citizen</option>
                <option value="1">Person w/ Disability</option>
                <option value="2">Solo Parent</option>
                <option value="3">National Athletes and Coaches</option>
            </select>
        </div>
        <div id="solo_parent_options" class="d-none">
            <div class="form-group fv-row mb-6">
                <label for="child_name" class="form-label required">Name of Child</label>
                <input type="text" class="form-control" id="child_name" name="child_name"/>
            </div>
            <div class="form-group fv-row mb-6">
                <label for="child_age" class="form-label required">Age of Child</label>
                <input type="number" class="form-control" id="child_age" name="child_age"/>
            </div>
        </div>
    </x-modals.create-edit>
    <x-modals.delete
            identifier="customer"
            title-identifier="Customer"
    ></x-modals.delete>
@endsection
@section('vendor-styles')
    <link href="{{ asset("assets/plugins/custom/datatables/datatables.bundle.css") }}" />
@endsection
@section('vendor-scripts')
    <script src="{{ asset("assets/plugins/custom/datatables/datatables.bundle.js") }}"></script>
@endsection
@section('scripts')
    
    <script src="{{ asset('assets/js/pages/special_customers/index.js') }}"></script>
@endsection