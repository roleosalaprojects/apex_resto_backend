@extends('layout.app')
@section('header')
    - Tax Management
@endsection
@section('title')
    {{ $tax->name }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}" class="">Home</a></li>
    <li class="breadcrumb-item text-muted">Categories</li>
@endsection
@section('actions')
    <input type="text" id="tableSearch" class="form-control form-control-sm" placeholder="Search table">
@endsection
@section('content')
    <div class="row">
        <div class="col-lg-3 mb-6">
            <div class="card card-flush">
                <div class="card-body">
                    <div class="d-flex flex-stack fs-4 fw-bold py-3">
                        Details
                    </div>
                    <div class="pb-5 fs-5">
                        <!--begin::Details item-->
                        <div class="fw-bold mt-5">Tax Name</div>
                        <div class="text-gray-600">{{ $tax->name }}</div>
                        <!--end::Details item-->
                        <!--begin::Details item-->
                        <div class="fw-bold mt-5">Tax Rate</div>
                        <div class="text-gray-600">{{ $tax->rate }} %</div>
                        <!--end::Details item-->
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-9">
            <div class="card card-flush">
                <div class="card-body">
                    <div class="d-flex flex-stack fs-4 fw-bold py-3">
                        Products
                    </div>
                    <table class="table table-hover" id="products_table">
                        <th>Product Name</th>
                        <th>Price</th>
                        <th></th>
                    </table>
                    <div class="tbody"></div>
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
        $(document).ready(function(){
            let table = $("#products_table")
            var tableOptions = {
                filter: true,
                responsive: true,
                serverside: true,
                processing: true,
                ajax: {
                    url: "{{ route('tax.show.table', $tax->id) }}",
                    dataSrc: function(response){
                        return response.data;
                    }
                },
                columns: [
                    {'data': 'name'},
                    {'data': 'price'},
                    {'data': 'actions'},
                ],
                columnDefs: [
                    {
                        targets: 1,
                        render: function(data, type, full){
                            let price = full.price;
                            let label = "text-success";
                            if(price === 0)
                            {
                                price = full.cost + (full.cost * (full.markup/100));
                                label = "text-danger";
                            }
                            return '\
                            <span class="'+label+'">'+price.toFixed(2)+'</span>\
                        ';
                        }
                    }
                ]
            }

            table.DataTable(tableOptions);

            $('#tableSearch').keyup(function(){
                table.DataTable().search($(this).val()).draw();
            });
        });
    </script>
@endsection
