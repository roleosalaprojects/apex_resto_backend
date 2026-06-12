@extends('layout.app')
@section('header')
    - Advertisements
@endsection
@section('title')
    Advertisements
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3 text-muted">Advertisements</li>
@endsection
@section('actions')
    <a href="{{ route('advertisements.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Create
    </a>
@endsection
@section('content')
    <div class="card shadow-sm">
        <div class="card-header bg-light border-0">
            <div class="card-title">
                <h3 class="fw-bold m-0">
                    <i class="fas fa-bullhorn text-primary me-2"></i>Advertisement Manager
                </h3>
            </div>
            <div class="card-toolbar">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge badge-light-success fs-7">
                        <i class="fas fa-check-circle me-1"></i>
                        <span id="activeCount">0</span> Active
                    </span>
                    <span class="badge badge-light-danger fs-7">
                        <i class="fas fa-times-circle me-1"></i>
                        <span id="inactiveCount">0</span> Inactive
                    </span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <x-data-table.table table-id="advertisementTable">
                <th class="min-w-80px">Preview</th>
                <th class="min-w-150px">Name</th>
                <th class="min-w-80px">Type</th>
                <th class="min-w-80px">Duration</th>
                <th class="min-w-60px">Order</th>
                <th class="min-w-80px">Status</th>
                <th class="text-end min-w-100px">Actions</th>
            </x-data-table.table>
        </div>
    </div>
@endsection
@section('modals')
    <x-modals.delete
        identifier="advertisement"
        title-identifier="Advertisement"
    ></x-modals.delete>
@endsection
@section('vendor-styles')
    <link rel="stylesheet" href="{{ asset("assets/plugins/custom/datatables/datatables.bundle.css") }}">
@endsection
@section('vendor-scripts')
    <script src="{{ asset("assets/plugins/custom/datatables/datatables.bundle.js") }}"></script>
@endsection
@section('scripts')
    <script src="{{ asset('assets/js/pages/advertisements/index.js') }}"></script>
@endsection
