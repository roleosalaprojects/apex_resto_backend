@extends('layout.app')
@section('header')
    - View Customer
@endsection
@section('title')
    {{$customer->name}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item "><a class="" href="{{route('customers.index')}}">Customers</a></li>
    <li class="breadcrumb-item  text-muted">{{$customer->name}}</li>
@endsection
@section('content')
    <div class="row">
        <div class="col-md-6">
            <div class="card card-custom mb-5">
                <div class="card-body">
                    <span class="float-right"><h4><strong>Accumulated Points: </strong>{{number_format($customer->accumulated_points, 2)}}</h4></span>
                </div>
            </div>
        </div>
        @if($customer->credit_limit > 0)
        <div class="col-md-6">
            <div class="card card-custom mb-5">
                <div class="card-body">
                    <h4 class="mb-3"><strong>Credit Account</strong></h4>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Credit Limit:</span>
                        <strong>₱ {{ number_format($customer->credit_limit, 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Outstanding Balance:</span>
                        <strong class="text-{{ $customer->credit_balance > 0 ? 'danger' : 'success' }}">₱ {{ number_format($customer->credit_balance, 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Available Credit:</span>
                        <strong class="text-success">₱ {{ number_format($customer->available_credit, 2) }}</strong>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
    <div class="row">
        <div class="col">
            <div class="card card-flush">
                <div class="card-header">
                    <div class="card-title">Purchase History</div>
                </div>
                <div class="card-body">
                    <table class="table table-hover" id="customers">
                        <thead>
                            <th>SI #</th>
                            <th>Acquired Points</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th></th>
                        </thead>
                        <tbody>
                            @foreach ($purchases as $purchase)
                                <tr>
                                    <td>{{$purchase->son}}</td>
                                    <td>{{$purchase->acquired_points}}</td>
                                    <td>₱ {{number_format($purchase->total, 2)}}</td>
                                    <td>{{Carbon\Carbon::parse($purchase->created_at)->format("M d, y h:i:s A")}}</td>
                                    <td>
                                        @if (auth()->user()->role->sls)
                                            {{-- <a href="{{route('show.receipts', $purchase->id)}}" class="btn btn-success btn-sm"><i class="far fa-eye"></i></a> --}}
                                            <a href="{{route('receipts.show', $purchase->id)}}" class="btn btn-light btn-active-success btn-sm btn-icon" target="_blank"><i class="far fa-eye"></i></a>
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
@section('vendor-styles')
    <link rel="stylesheet" href="{{asset("assets/plugins/custom/datatables/datatables.bundle.css")}}">
@endsection
@section('vendor-scripts')
    {{-- DataTables --}}
    <script src="{{asset("assets/plugins/custom/datatables/datatables.bundle.js")}}"></script>
@endsection
@section('scripts')
    <script>
        //TODO::create an export functionality
        $(function(){
            $("#customers").DataTable({
                "responsive": true, "lengthChange": true, "autoWidth": true,
                "buttons": ["excel", "print"]
            }).buttons().container().appendTo('#customers_wrapper .col-md-6:eq(0)');
        })
    </script>
@endsection
