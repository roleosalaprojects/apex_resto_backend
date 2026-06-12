@extends('layout.app')
@section('header')
    - Contact Messages
@endsection
@section('title')
    Contact Messages
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3 text-muted">Contact Messages</li>
@endsection
@section('content')
    <div class="card shadow-sm">
        <div class="card-header bg-light border-0">
            <div class="card-title">
                <h3 class="fw-bold m-0">
                    <i class="fas fa-envelope text-primary me-2"></i>Contact Form Messages
                </h3>
            </div>
            <div class="card-toolbar">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted fs-7">Messages received from the landing page contact form</span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <x-data-table.table table-id="contactMessageTable">
                <th class="min-w-80px">Status</th>
                <th class="min-w-150px">Name</th>
                <th class="min-w-150px">Email</th>
                <th class="min-w-120px">Subject</th>
                <th class="min-w-120px">Date</th>
                <th class="text-end min-w-100px">Actions</th>
            </x-data-table.table>
        </div>
    </div>
@endsection
@section('modals')
    <x-modals.delete
        identifier="contactMessage"
        title-identifier="ContactMessage"
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
    let table = $('#contactMessageTable');
    let url = '{{ route("contact-messages.index") }}';
    let titleIdentifier = 'ContactMessage';

    let $options = {
        responsive: true,
        serverSide: true,
        processing: true,
        ajax: {
            url: "{{ route('contact-messages.table') }}",
            dataSrc: function(response) {
                return response.data;
            }
        },
        columns: [
            { data: 'status_badge' },
            { data: 'name' },
            { data: 'email' },
            { data: 'subject_label' },
            { data: 'date' },
            { data: 'actions' }
        ],
        order: [[4, 'desc']]
    };

    table.DataTable($options);

    $('#tableSearch').keyup(function() {
        table.DataTable().search($(this).val()).draw();
    });

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
