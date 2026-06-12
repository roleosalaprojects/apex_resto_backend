@extends('layout.app')
@section('header')
    - View Stock Adjustment
@endsection
@section('title')
    Stock Adjustment #: {{ $adjustment->so }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item "><a class="" href="{{route('adjustments.index')}}">Stock Adjustments</a></li>
    <li class="breadcrumb-item  text-muted">SO #: {{$adjustment->so}}</li>
@endsection
@section('actions')
    @if ($adjustment->status == 2)
        @if ($access->adjstmnts_update)
            <a href="{{route('adjustments.edit', $adjustment->id)}}" class="btn btn-info btn-sm ki-text-align-center">Edit</a>
            <button class="btn btn-danger btn-sm" id="btnApprove">Approve</button>
            <form action="{{ route('adjustment.approve', $adjustment->id) }}" method="post" id="approveForm">
                @csrf
            </form>
        @endif
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card card-flush">
                <div class="card-body">
                    <div class="row mb-7">
                        <div class="col-6">
                            Created by: {{$adjustment->creator}}
                        </div>
                        <div class="col-6">
                            Date Created: {{date('M d, Y - h:i A', strtotime($adjustment->created_at))}}
                        </div>
                    </div>
                    <br>
                    <div class="row mb-7">
                        <div class="col-6">
                            Store: {{$adjustment->store}}
                        </div>
                        <div class="col-6">
                            Approved by: @if ($adjustment->receiver)
                                <span class="text-success">{{$adjustment->receiver}}</span>
                            @else
                                <span class="text-danger">Not yet received</span>
                            @endif
                        </div>
                    </div>
                    <div class="my-10 separator"></div>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <td style="width: 40%">Name</td>
                                <td style="width: 25%">Unit</td>
                                <td style="width: 30%">To adjust</td>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            @foreach ($adjustment_line as $adjustment)
                                <tr>
                                    <td>{{$adjustment->item}}</td>
                                    <td>
                                        @if ($adjustment->unit == 'PCS')
                                            {{$adjustment->unit}}
                                        @else
                                            {{$adjustment->unit}} ({{$adjustment->unit_qty}} pcs)
                                        @endif
                                    </td>
                                    <td>{{number_format($adjustment->qty, 2)}}</td>
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
            let btnApprove = $("#btnApprove");
            btnApprove.on('click', function(e){
                $("#approveForm").submit();
            })
        })
    </script>
@endsection