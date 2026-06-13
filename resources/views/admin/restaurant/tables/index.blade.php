@extends('layout.app')
@section('header')
    - Tables
@endsection
@section('title')
    Restaurant Tables
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item text-muted">Tables</li>
@endsection
@section('actions')
    @if ($access->rstrnt_create)
        <a href="{{route('restaurant-tables.create')}}" class="btn btn-primary">Create</a>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <x-data-table.table table-id="tablesTable">
                        <th>Name</th>
                        <th>Number</th>
                        <th>Area</th>
                        <th>Seats</th>
                        <th>Status</th>
                        <th></th>
                    </x-data-table.table>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}">
@endsection
@section('vendor-scripts')
    <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
@endsection
@section('scripts')
    <script>
        $(document).ready(function () {
            $("#tablesTable").DataTable({
                responsive: true,
                serverside: true,
                processing: true,
                columns: [
                    {data: 'name'},
                    {data: 'number'},
                    {data: 'area'},
                    {data: 'seats'},
                    {data: 'status_label'},
                    {data: 'actions'},
                ],
                columnDefs: [{targets: -1, orderable: false}],
                ajax: {dataSrc: 'data', url: '{{ route('restaurant-tables.table') }}'},
            });
        });
    </script>
@endsection
