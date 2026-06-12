@extends('admin.printer.v2')
@section('header')
    PO #: {{ $purchase->po }}
@endsection
@section('content')
    <div class="container">
        <div class="row">
            <div class="col-4">
                <div class="mb-4">
                    <span class="fw-bold fs-3">PO #: {{$purchase->po}}</span>
                </div>
                <div>
                    <span class="fw-bold">Supplier :</span>
                    <address>
                        {{$purchase->supplier->name}}
                    </address>
                </div>
                <div>
                    <span class="fw-bold">Created By :</span>
                    <address>
                        {{$purchase->creator->name}}
                    </address>
                </div>
                <div>
                    <span class="fs-6 fw-semibold">Terms:</span>
                    <span class="">
                    {{ (int)$purchase->expected }}
                </span>
                    Days
                </div>
            </div>
            <div class="col-4">
                <div class="mb-4">
                    <span class="fw-bold fs-3">Invoice # :&nbsp{{$purchase->invoice_no}}</span>
                </div>
                <div class="mb-4">
                <span class="">
                    Purchase Date: {{\Carbon\Carbon::parse($purchase->purchased)->format('M d, Y')}}
                </span>
                </div>
                <div class="mb-3">
                    <span class="fw-bold">Destination Store :</span>
                    <address>
                        {{$purchase->store->name}}
                    </address>
                </div>
                <div>
                    Due Date:
                    <span class="text-danger">
                    {{ \Carbon\Carbon::parse($purchase->purchased)->addDays($purchase->expected-1)->format('M d, Y') }}
                </span>
                </div>
            </div>
            <div class="col-4">
                <div>
                    <span class="fw-bold">Date :</span>
                    <address>
                        <i>{{ \Carbon\Carbon::parse($purchase->created_at)->format('M-d-Y h:i A') }}</i>
                    </address>
                </div>
                <div class="mb-3">
                <span class="fw-bold">Status :
                            @if ($purchase->status == 1)
                        <span class="text-warning">Pending</span>
                    @else
                        @if ($purchase->items - $purchase->received > 0)
                            <span class="text-warning">Partially Received</span>
                        @else
                            <span class="text-success">Received</span>
                        @endif
                    @endif
                </span>
                    <br>
                    <span class="fw-bold">{{$purchase->received}} of {{$purchase->items}} received</span>
                </div>
                <div>
                    <span class="fw-bold">Received By</span>
                    <address>
                        {{($purchase->received_by) ? $purchase->receiver->name : "N/A"}}
                    </address>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="separator separator-content border-dark my-3"><span
                        class="fs-6 fw-semibold">Items/Products</span></div>
            <div class="col-12">
                <div class="table-responsive text-black fs-9">
                    <table class="table gs-2 gy-2 gx-2">
                        <thead>
                        <tr class="fw-bold fs-8 border-bottom border-black">
                            <th>Description</th>
                            <th>Unit</th>
                            <th>Qty</th>
                            <th>Received</th>
                            <th>Price</th>
                            <th>Sub Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php
                            $totalItems = 0;
                        @endphp
                        @foreach ($purchase->lines as $line)
                            @php
                                $unit_qty = 1;
                                $sub_total = 0;
                            @endphp
                            <tr>
                                <td>
                                    {{$line->item->name}}
                                </td>
                                <td>
                                    @if ($line->unit_id == null)
                                        PCS
                                    @else
                                        @foreach ($line->item->itemUnits as $item_unit)
                                            @if ($item_unit->unit_id == $line->unit_id)
                                                {{ $item_unit->unit->name }} ({{ $item_unit->qty }})
                                                @php
                                                    $unit_qty = $item_unit->qty
                                                @endphp
                                            @endif
                                        @endforeach
                                    @endif
                                </td>
                                @php
                                    $sub_total = $line->qty * $unit_qty * $line->cost;
                                    $totalItems += $sub_total;
                                @endphp
                                <td>{{$line->qty}}</td>
                                <td>{{$line->received}}</td>
                                <td>₱ {{ number_format($line->cost, 2) }}</td>
                                <td>₱ {{ number_format($sub_total, 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td><span class="fw-bold text-black">Sub Total:</span></td>
                            <td>
                                {{ number_format($totalItems, 2) }}
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-6"></div>
            <div class="col-sm-6">
                @if (count($purchase->adds) > 0)
                    <div class="separator separator-content my-15"><h5>Additionals</h5></div>
                    <table class="table">
                        <thead>
                        <th>Description</th>
                        <th>Amount</th>
                        </thead>
                        <tbody>
                        @foreach ($purchase->adds as $add)
                            <tr>
                                <td>
                                    {{$add->description}}
                                </td>
                                <td>
                                    ₱ {{number_format($add->amount, 2)}}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
                <div class="separator border-dark my-6"></div>
                <div class="d-flex justify-content-between">
                    <h3>Grand Total : </h3>
                    <h2>₱ {{number_format($purchase->total, 2)}}</h2>
                </div>
            </div>
        </div>
    </div>
@endsection
