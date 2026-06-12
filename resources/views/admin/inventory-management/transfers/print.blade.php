@extends('admin.printer.v2')
@section('content')
    <div class="container">
        <div class="row invoice-info">
            <div class="col-sm-6 invoice-col">
                <h4>
                    <strong>TO #: {{ $transfer->to }}</strong>
                    <address>
                        {{$transfer->po}}
                    </address>
                </h4>
            </div>
            <div class="col-sm-6 invoice-col">
                <strong>Status :
                    @if ($transfer->status == 2)
                        <span class="text-warning">Pending</span>
                    @endif
                    @if($transfer->status == 1)
                        <span class="text-success">Received</span>
                    @endif
                    @if($transfer->status == 0)
                        <span class="text-danger">Deleted</span>
                    @endif
                </strong>
            </div>
        </div>
        <div class="row invoice-info">
            <div class="col-sm-6 invoice-col">
                <span class="fw-bold">Source :</span>
                <address>
                    {{$transfer->source}}
                </address>
            </div>
            <div class="col-sm-6 invoice-col">
                <span class="fw-bold">Destination Store :</span>
                <address>
                    {{$transfer->destination}}
                </address>
            </div>
        </div>
        <div class="row invoice-info">
            <div class="col-sm-6 invoice-col">
                <span class="fw-bold">Created By :</span>
                <address>
                    {{$transfer->creator}}
                </address>
            </div>
            <div class="col-sm-6 invoice-col">
                <span class="fw-bold">Received By</span>
                <address>
                    {{($transfer->receiver) ? $transfer->receiver : "N/A"}}
                </address>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-6">
                <span class="fw-bold">Creation Date:</span>
                <address>
                    {{ Carbon\Carbon::parse($transfer->create_at)->format('Y-m-d h:i A') }}
                </address>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <h4>Transferred Items</h4>
            </div>
            <div class="col-12">
                <table class="table">
                    <thead>
                    <tr class="fw-bold fs-6 text-gray-800">
                        <th>Name</th>
                        <th>Unit</th>
                        <th>Transferred</th>
                        <th>To receive</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($transfer_line as $transfer)
                        <tr>
                            <td>{{$transfer->item}}</td>
                            <td>
                                @if ($transfer->unit == 'PCS')
                                    {{$transfer->unit}}
                                @else
                                    {{$transfer->unit}} ({{$transfer->unit_qty}} pcs)
                                @endif
                            </td>
                            <td>{{$transfer->qty}}</td>
                            <td>{{$transfer->qty - $transfer->received}}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
