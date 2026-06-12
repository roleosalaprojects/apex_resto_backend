@extends('layout.app')
@section('title')
    {{$supplier->name}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a  class="" href="{{route('suppliers.index')}}">Suppliers</a></li>
    <li class="breadcrumb-item text-muted">{{$supplier->name}}</li>
@endsection
@section('content')
    <div class="row">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Items</h3>
                </div>
                <div class="card-body">
                    <table class="table" id="tblSuppliers">
                        <thead>
                            <th>Item</th>
                            <th>Price</th>
                            <th>Cost</th>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td><a href="{{route("items.show", $item->id)}}">{{$item->name}}</a></td>
                                    <td>{{number_format($item->price, 2)}}</td>
                                    <td>{{number_format($item->cost, 2)}}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {!! Form::open(["route"=>["supplier.store.items", $supplier->id]]) !!}
                    <div>
                        <div class="form-group">
                            {!! Form::label("items[]", "Add Items", []) !!}
                            <select class="form-select" id="items" name="items[]" multiple="multiple" data-control="select2" data-placeholder="Select an option" required>
                                {{-- Items --}}
                                <option></option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex align-content-end">
                        <div class="form-group mt-6">
                            <button type="submit" class="btn btn-info ">Update</button>
                        </div>
                    </div>
                    {!! Form::close() !!}
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Purchase Orders</h3>
                </div>
                <div class="card-body">
                    <table class="table" id="tblPurchases">
                        <thead>
                            <th>PO #</th>
                            <th>Amount</th>
                            <th>Created By</th>
                            <th>Remarks</th>
                        </thead>
                        <tbody>
                            @foreach ($supplier->purchaseOrders as $purchase)
                                <tr>
                                    <td><a href="{{route('purchases.show', $purchase->id)}}">{{$purchase->po}}</a></td>
                                    <td>{{number_format($purchase->total, 2)}}</td>
                                    <td>
                                        {{$purchase->creator->name}}
                                    </td>
                                    <td>
                                        @if ($purchase->payment_status == 1)
                                            <span class="badge badge-success">Paid</span>
                                        @else
                                            <span class="badge badge-danger">Unpaid</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        $(document).ready(function(){
            init();
            function init(){
                getItems();
            }

            function getItems(){
                $("#items").select2({
                    allowClear: true,
                    minimumInputLength: 3,
                    ajax: {
                        url: '{{route("supplier.items")}}',
                        delay: 250,
                        type: "get",
                        dataType: 'json',
                        data: function (params) {
                            var query = {
                                search: params.term,
                            }

                            // Query parameters will be ?search=[term]&type=public
                            return query;
                        },
                        processResults: function (data) {
                            // console.log(data);
                            return {
                                results: data
                            };
                        },
                        cache: true
                    }
                });
            }
        });
    </script>
@endsection