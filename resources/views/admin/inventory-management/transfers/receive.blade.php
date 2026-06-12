@extends('layout.app')
@section('header')
    - Receive Transfer Order
@endsection
@section('title')
    Receive Transfer Order
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('transfers.index')}}">Transfer Orders</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('transfers.show', $transfer->id)}}">TO #: {{$transfer->to}}</a></li>
    <li class="breadcrumb-item text-muted">Receive TO #: {{$transfer->to}}</li>
@endsection
@section('actions')
    <button class="btn btn-success btn-sm" onclick="fillAll()">Mark all as received</button>
    {!! Form::submit("Receive Now", ["class"=>'btn btn-sm btn-danger', 'form'=>'receiveForm']) !!}
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Transfer Details</div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <h2>TO #{{$transfer->to}}</h2>
                            @if($transfer->status == 2) 
                                <span class="text-warning">Pending</span>
                            @endif
                            @if ($transfer->status == 1)
                                @if ($transfer->qty - $transfer->received != 0)
                                    <span class="text-warning">Partially received</span>
                                @else
                                    <span class="text-succes">Received</span>
                                @endif
                            @endif
                        </div>
                        <div class="col-4">
                            <div class="progress progress-xs">
                                <div class="progress-bar @if($transfer->total - $transfer->received != 0) bg-info @else bg-success @endif progress-bar-striped" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: {{($transfer->received) ? ($transfer->received / $transfer->total) * 100 : 0}}%"></div>
                            </div>
                            <small>{{$transfer->received}} of {{$transfer->total}} received</small>
                        </div>
                    </div>
                    <br>
                    <div class="row">
                        <div class="col-12">
                            <p><strong>Date: {{date('M d, Y - h:i A', strtotime($transfer->created_at))}}</strong> </p>
                            <p><strong>Created by:</strong> {{$transfer->created_by}}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <strong>Source store:</strong> <br> {{$transfer->source_store}}
                        </div>
                        <div class="col-6">
                            <strong>Destination Store:</strong> <br> {{$transfer->destination_store}}
                        </div>
                    </div>
                    <br>
                    <h4>Items</h4>
                {!! Form::open(['route'=>['transfers.receive.now', $transfer->id], 'id'=>'receiveForm']) !!}
                    <table class="table table-hover" id="receiveTable">
                        <thead>
                            <tr>
                                <td>Name</td>
                                <td>Unit</td>
                                <td>Transferred</td>
                                <td>To receive</td>
                                <td>Received</td>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            @foreach ($transfer_line as $transfer)
                                @if ($transfer->qty - $transfer->received)
                                    <tr>
                                        <td>
                                            {!! Form::hidden("item_id[]", $transfer->item_id, []) !!}
                                            {!! Form::hidden("transfer_line_id[]", $transfer->id, []) !!}
                                            {{$transfer->item}}
                                        </td>
                                        <td>
                                            @if ($transfer->unit == 'PCS')
                                                {{$transfer->unit}}
                                            @else
                                                {{$transfer->unit}} ({{$transfer->unit_qty}} pcs)
                                            @endif
                                        </td>
                                        <td><span class="">{{$transfer->received}}</span></td>
                                        <td><span class="qty">{{$transfer->qty - $transfer->received}}</span></td>
                                        <td>
                                            <input name="toReceive[]" type="number" class="form-control toReceive" onkeyup="return isNumberKey(event)" oninput='limitDecimalPlaces(event, 3)' max="{{$transfer->qty - $transfer->received}}" min="0" required>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        var optionals = 0;
        $(document).ready(function(){
            $(".toReceive").change( function(){
                // calculate();
                // console.log($(this).closest('tr').find('.toReceive').val());
                var input = $(this).closest('tr').find('.toReceive').val()
                var qty = $(this).closest('tr').find('.qty').text()
                if(qty - input < 0){
                    // $(this).closest('tr').find('.toReceive').addClass('is-invalid')
                    $(this).closest('tr').find('.toReceive').val(qty);
                    alertWarning("Can't receive more than of what you transfered!");
                }
                else if(qty - input > qty){
                    $(this).closest('tr').find('.toReceive').val(qty);
                    alertWarning("No negative number is allowed!");
                }
                // else if(qty - input == qty){
                //     $(this).closest('tr').find('.toReceive').val(qty);
                //     alertWarning("0 is not allowed. Setting to maximum receivable");
                // }
                else{
                    $(this).closest('tr').find('.toReceive').removeClass('is-invalid')
                    if(optionals > 0){
                        optionals--;
                    }
                    
                }
                console.log(optionals);
            });
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 6000
            });
        });
        function fillAll(){
            $("#receiveTable tbody tr").each(function() {
                var value = $(this).find(" td:nth-child(4)").text();
                $(this).find(" td:nth-child(5) input").val(value);
            });
        }
        function alertWarning(msg) {
            toastr.warning(msg)
        }
        // function getMaxNumber(value){
        //     console.log(value);
        //     value.val(value.max);
        // }
    </script>
@endsection