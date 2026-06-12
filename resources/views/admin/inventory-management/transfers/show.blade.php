@extends('layout.app')
@section('header')
    - Transfer Order Details
@endsection
@section('title')
    TO #: {{ $transfer->to }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('transfers.index')}}">Transfer Orders</a></li>
    <li class="breadcrumb-item text-muted">TO #: {{$transfer->to}}</li>
@endsection
@section('actions')
    @if ($transfer->qty - $transfer->total != 0)
        @if ($transfer->status == 2)
            <a href="{{route('transfers.edit', $transfer->id)}}" class="btn btn-sm btn-info">Edit</a>
        @endif
        @if ($transfer->total - $transfer->received != 0)
            <a href="{{route('transfers.receive', $transfer->id)}}" class="btn btn-sm btn-danger">Receive</a>
        @endif
    @endif
    @if ($access->trnsfrs_read)
        <a href="{{ route('transfers.print', $transfer->id) }}" class="btn btn-sm btn-bg-light btn-active-dark" rel="noopener" target="_blank">Print</a>
    @endif
@endsection
@section('content')
    <div class="row mb-10">
        <div class="col">
            <div class="card card-flush">
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
                                @if ($transfer->total - $transfer->received != 0)
                                    <span class="text-warning">Partially received</span>
                                @else
                                    <span class="text-success">Received</span>
                                @endif
                            @endif
                        </div>
                        <div class="col-4">
                            <div class="progress progress-xs">
                                <div class="progress-bar @if($transfer->total - $transfer->received != 0) bg-warning @else bg-primary @endif progress-bar-striped" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: {{($transfer->received) ? ($transfer->received / $transfer->total) * 100 : 0}}%"></div>
                            </div>
                            <small>{{$transfer->received}} of {{$transfer->total}} received</small>
                        </div>
                    </div>
                    <br>
                    <div class="row">
                        <div class="col-6">
                            <p><strong>Date: {{date('M d, Y - h:i A', strtotime($transfer->created_at))}}</strong> </p>
                            <p><strong>Created by: </strong><span class="text-primary">{{$transfer->creator->name}}</span></p>
                        </div>
                        <div class="col-6">
                            <p><strong>Updated by: </strong><span class="text-info">{{$transfer->updater ? $transfer->updater->name : 'N/A'}}</span></p>
                            <p><strong>Received by: </strong> <span class="text-success">{{$transfer->receiver ? $transfer->receiver->name : 'N/A'}}</span></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <p><strong>Delivered By: </strong><span class="text-primary">{{($transfer->delivery) ? $transfer->delivery : "N/A"}}</span></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <strong>Source store:</strong> <br> {{$transfer->source->name}}
                        </div>
                        <div class="col-6">
                            <strong>Destination Store:</strong> <br> {{$transfer->destination->name}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col">
            <div class="card card-flush">
                <div class="card-header">
                    <div class="row">
                        <div class="card-title">Transfer Details</div>
                    </div>
                </div>
                <div class="card-body">

                    <br>
                    <h4>Items</h4>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <td style="width: 55%">Name</td>
                                <td style="width: 15%">Unit</td>
                                <td style="width: 15%">Transferred</td>
                                <td style="width: 15%">To receive</td>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            {{-- @foreach ($transfer_line as $transfer)
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
                            @endforeach --}}
                            @foreach ($transfer->lines as $line)
                                <tr>
                                    <td>{{ $line->item->name }}</td>
                                    <td>{{ ($line->unit_item) ? $line->unit_item->name . " ($line->unit_qty pcs)" : 'PCS' }}</td>
                                    <td>{{ $line->received }}</td>
                                    <td>{{ $line->qty - $line->received }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
