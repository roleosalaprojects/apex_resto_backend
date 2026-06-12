@extends('layout.app')
@section('header')
    - Vouchers
@endsection
@section('title')
    Vouchers
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3 text-muted">Vouchers</li>
@endsection
@section('actions')
    <a href="{{ route('vouchers.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Create Voucher
    </a>
@endsection
@section('content')
    <div class="card shadow-sm">
        <div class="card-header bg-light border-0">
            <div class="card-title">
                <h3 class="fw-bold m-0">
                    <i class="ki-duotone ki-ticket text-primary fs-1 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                    Voucher Management
                </h3>
            </div>
            <div class="card-toolbar">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted fs-7">Discount vouchers for POS</span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <x-data-table.table table-id="voucherTable">
                <th class="min-w-100px">Code</th>
                <th class="min-w-150px">Name</th>
                <th class="min-w-80px">Amount</th>
                <th class="min-w-100px">Usage</th>
                <th class="min-w-100px">Store</th>
                <th class="min-w-100px">Expires</th>
                <th class="min-w-80px">Status</th>
                <th class="text-end min-w-100px">Actions</th>
            </x-data-table.table>
        </div>
    </div>
@endsection
@section('modals')
    <x-modals.delete
        identifier="voucher"
        title-identifier="Voucher"
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
    let table = $('#voucherTable');
    let url = '{{ route("vouchers.index") }}';
    let titleIdentifier = 'Voucher';

    let options = {
        responsive: true,
        serverSide: true,
        processing: true,
        ajax: {
            url: "{{ route('vouchers.table') }}",
            dataSrc: function(response) {
                return response.data;
            }
        },
        columns: [
            { data: 'code' },
            { data: 'name' },
            { data: 'amount', render: function(data) { return '₱' + parseFloat(data).toFixed(2); } },
            { data: 'usage' },
            { data: 'store_name' },
            { data: 'expires_at', render: function(data) { return new Date(data).toLocaleDateString(); } },
            { data: 'status' },
            { data: 'actions' }
        ],
        order: [[5, 'asc']]
    };

    table.DataTable(options);

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
