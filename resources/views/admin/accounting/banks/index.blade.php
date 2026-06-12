@extends('layout.app')
@section('header')
    - Banking
@endsection
@section('title')
    Banking
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">Banking</li>
@endsection
@section('actions')
    <x-data-table.actions></x-data-table.actions>
    <x-general.search-table
            title="Bank"
    ></x-general.search-table>
    <x-modals.create-button
        identifier="bank"
    ></x-modals.create-button>
@endsection
@section('content')
    <div class="card">
        <div class="card-body">
            <x-data-table.table
                table-id="bankTable"
            >
                <th>Bank</th>
                <th class="min-w-150px">Account Name</th>
                <th>Account #</th>
                <th>Balance</th>
                <th></th>
            </x-data-table.table>
        </div>
    </div>
@endsection
@section('modals')
    <x-modals.create-edit
            identifier="bank"
            title="Bank"
    >
        <div class="mb-6 form-group fv-row">
            <label for="bank_name" class="form-label required">Bank Name</label>
            <input type="text" class="form-control" name="bank_name" id="bank_name">
        </div>
        <div class="mb-6 form-group fv-row">
            <label for="account_name" class="form-label required">Account Name</label>
            <input type="text" class="form-control" name="account_name" id="account_name">
        </div>
        <div class="mb-6 form-group fv-row">
            <label for="account_number" class="form-label required">Account Number</label>
            <input type="text" class="form-control" name="account_number" id="account_number">
        </div>
        <div class="mb-6 form-group fv-row">
            <label for="account_type" class="form-label required">Account Type</label>
            <select name="account_type" id="account_type" class="form-select">
                <option></option>
                <option value="0">Debit / Savings</option>
                <option value="1">Checking</option>
                <option value="2">Credit</option>
                <option value="3">Passbook</option>
                <option value="4">E-Wallet</option>
            </select>
        </div>
        <div class="mb-6 form-group fv-row">
            <label for="starting_balance" class="form-label required">Starting Balance</label>
            <input type="number" name="starting_balance" id="starting_balance" class="form-control" min="0">
        </div>
        <div class="mb-6 form-group fv-row">
            <label for="description" class="form-label required">Description</label>
            <textarea name="description" id="description" class="form-control" data-kt-autosize="true"></textarea>
        </div>
    </x-modals.create-edit>
    <x-modals.delete
            identifier="bank"
            title-identifier="Bank"
    ></x-modals.delete>
@endsection
@section('vendor-styles')
    <link href="{{ asset("assets/plugins/custom/datatables/datatables.bundle.css") }}" />
@endsection
@section('vendor-scripts')
    <script src="{{ asset("assets/plugins/custom/datatables/datatables.bundle.js") }}"></script>
@endsection
@section('scripts')
    
    <script src="{{ asset('assets/js/pages/banks/index.js') }}"></script>
@endsection