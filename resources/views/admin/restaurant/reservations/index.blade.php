@extends('layout.app')
@section('header')
    - Reservations
@endsection
@section('title')
    Reservations
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item text-muted">Reservations</li>
@endsection
@section('actions')
    @if ($access->rstrnt_create)
        <a href="{{route('reservations.create')}}" class="btn btn-primary">Create</a>
    @endif
@endsection
@section('content')
    <div class="row mb-5">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><h3>Calendar</h3></div>
                </div>
                <div class="card-body">
                    <div id="reservationCalendar"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <x-data-table.table table-id="reservationsTable">
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Party</th>
                        <th>Reserved At</th>
                        <th>Table</th>
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
    <link rel="stylesheet" href="{{ asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.css') }}">
@endsection
@section('vendor-scripts')
    <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
    <script src="{{ asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.js') }}"></script>
@endsection
@section('scripts')
    <script>
        $(document).ready(function () {
            $("#reservationsTable").DataTable({
                responsive: true,
                serverside: true,
                processing: true,
                columns: [
                    {data: 'name'},
                    {data: 'phone', defaultContent: '—'},
                    {data: 'party_size'},
                    {data: 'reserved_at'},
                    {data: 'table.name', defaultContent: '—'},
                    {data: 'status'},
                    {data: 'actions'},
                ],
                columnDefs: [{targets: -1, orderable: false}],
                ajax: {dataSrc: 'data', url: '{{ route('reservations.table') }}'},
            });

            var calendarEl = document.getElementById('reservationCalendar');
            if (calendarEl && typeof FullCalendar !== 'undefined') {
                new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek'},
                    events: '{{ route('reservations.calendar-events') }}',
                }).render();
            }
        });
    </script>
@endsection
