@extends('layout.app')
@section('header')
    - Ecommerce Orders
@endsection
@section('title')
    Ecommerce Orders
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item text-muted">Ecommerce Orders</li>
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card card-flush">
                <div class="card-body">
                    <x-general.data-table table-id="tblEcommerceOrders">
                        <th>Reference</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </x-general.data-table>
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
    $(function(){
        let table = $("#tblEcommerceOrders");
        let $options = {
            filter: true,
            responsive: true,
            serverside: true,
            processing: true,
            ajax: {
                url: "{{ route('ecommerce-orders.table') }}",
                dataSrc: function(response){
                    return response.data;
                }
            },
            columns: [
                {'data': 'reference'},
                {'data': 'customer'},
                {'data': 'qty'},
                {'data': 'total'},
                {'data': 'status'},
                {'data': 'created_at'},
                {'data': 'actions'}
            ],
            columnDefs: [
                {
                    targets: 1,
                    render: function(data, type, full) {
                        return full.customer ? full.customer.name : 'N/A';
                    }
                },
                {
                    targets: 3,
                    render: function(data) {
                        return parseFloat(data).toFixed(2);
                    }
                },
                {
                    targets: 4,
                    render: function(data) {
                        if (data === 0) return '<span class="badge badge-light-warning">Pending</span>';
                        if (data === 1) return '<span class="badge badge-light-success">Verified</span>';
                        return '<span class="badge badge-light-danger">Cancelled</span>';
                    }
                },
                {
                    targets: 5,
                    render: function(data) {
                        if (!data) return '';
                        return new Date(data).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                    }
                }
            ],
            order: [[5, 'desc']]
        };

        table.DataTable($options);

        $('#tableSearch').keyup(function(){
            table.DataTable().search($(this).val()).draw();
        });
    });
</script>
@endsection
