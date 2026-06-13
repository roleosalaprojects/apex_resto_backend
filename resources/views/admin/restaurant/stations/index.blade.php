@extends('layout.app')
@section('header')
    - Kitchen Stations
@endsection
@section('title')
    Kitchen Stations
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item text-muted">Kitchen Stations</li>
@endsection
@section('actions')
    @if ($access->rstrnt_create)
        <a href="{{route('kitchen-stations.create')}}" class="btn btn-primary">Create</a>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <x-data-table.table table-id="stationsTable">
                        <th>Name</th>
                        <th>Store</th>
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
            $("#stationsTable").DataTable({
                responsive: true,
                serverside: true,
                processing: true,
                columns: [
                    {data: 'name'},
                    {data: 'store.name', defaultContent: '—'},
                    {data: 'actions'},
                ],
                columnDefs: [{targets: -1, orderable: false}],
                ajax: {dataSrc: 'data', url: '{{ route('kitchen-stations.table') }}'},
            });
        });
    </script>
@endsection
