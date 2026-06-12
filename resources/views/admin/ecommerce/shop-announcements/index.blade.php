@extends('layout.app')
@section('header')
    - Shop Announcements
@endsection
@section('title')
    Shop Announcements
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3 text-muted">Shop Announcements</li>
@endsection
@section('actions')
    <a href="{{ route('shop-announcements.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Create Announcement
    </a>
@endsection
@section('content')
    <div class="card shadow-sm">
        <div class="card-header bg-light border-0">
            <div class="card-title">
                <h3 class="fw-bold m-0">
                    <i class="fas fa-bullhorn text-primary me-2"></i>Shop Announcement Manager
                </h3>
            </div>
            <div class="card-toolbar">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted fs-7">Announcements displayed on the online shop</span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <x-data-table.table table-id="shopAnnouncementTable">
                <th class="min-w-80px">Preview</th>
                <th class="min-w-150px">Title</th>
                <th class="min-w-80px">Type</th>
                <th class="min-w-80px">Position</th>
                <th class="min-w-100px">Schedule</th>
                <th class="min-w-60px">Order</th>
                <th class="min-w-80px">Status</th>
                <th class="text-end min-w-100px">Actions</th>
            </x-data-table.table>
        </div>
    </div>
@endsection
@section('modals')
    <x-modals.delete
        identifier="shopAnnouncement"
        title-identifier="ShopAnnouncement"
    ></x-modals.delete>
@endsection
@section('vendor-styles')
    <link rel="stylesheet" href="{{ asset("assets/plugins/custom/datatables/datatables.bundle.css") }}">
@endsection
@section('vendor-scripts')
    <script src="{{ asset("assets/plugins/custom/datatables/datatables.bundle.js") }}"></script>
@endsection
@section('scripts')
<script>
$(function() {
    let table = $('#shopAnnouncementTable');
    let url = '{{ route("shop-announcements.index") }}';
    let titleIdentifier = 'ShopAnnouncement';

    let $options = {
        responsive: true,
        serverSide: true,
        processing: true,
        ajax: {
            url: "{{ route('shop-announcements.table') }}",
            dataSrc: function(response) {
                return response.data;
            }
        },
        columns: [
            { data: 'preview' },
            { data: 'title' },
            { data: 'type_badge' },
            { data: 'position_badge' },
            { data: 'schedule' },
            { data: 'display_order' },
            { data: 'status_badge' },
            { data: 'actions' }
        ],
        order: [[5, 'asc']]
    };

    table.DataTable($options);

    $('#tableSearch').keyup(function() {
        table.DataTable().search($(this).val()).draw();
    });

    // Delete handler
    handleDelete(
        titleIdentifier,
        table,
        $('#delete' + titleIdentifier + 'Modal'),
        document.querySelector('#btnDelete' + titleIdentifier),
        document.querySelector('#delete' + titleIdentifier + 'Form'),
        url
    );
});
</script>
@endsection
