@extends('admin.printer.custom')
@section('content')
    <div class="ticket">
        <h4 class="text-center title">
            {{$sale->store}}
        </h4>
        <h4 class="text-center">
            {{$sale->header}}
        </h4>
        <h4 class="text-center">
            Tin: {{$sale->TIN}}
        </h4>
        <h4 class="text-center">
            PN: {{$sale->pn}}
        </h4>
        <h4 class="text-center">
            email: {{$sale->email}}
        </h4>
        <h4 class="text-center">
            MIN : {{$sale->min}}
        </h4>
        <h4 class="text-center">
            Serial #: {{$sale->serial}}
        </h4>
        <h5 class="text-justify">
            Date: {{$sale->date}}
        </h5>
        <h5 class="text-justify">
            Cashier: {{$sale->sold_by}}
        </h5>
        <h4 class="text-justify">
            SI #: {{$sale->son}}
        </h4>
        @if ($sale->type == true)
            <h4 class="text-center text-danger">REFUND</h4>
        @endif

        @php
            $total = 0;
        @endphp

        <br>

        <hr>
        @foreach ($lines as $line)
            @php
                $total += $line->qty;
            @endphp
            <div>
                <h5>
                    {{$line->item}}
                </h5>
            </div>
            <div class="d-flex justify-content-between">
                <div class="div">
                    <h5>
                        {{$line->qty}} {{$line->unit}} x ₱ {{$line->price}}
                    </h5>
                </div>
                <div class="div">
                    <h5>
                        {{$line->sub_total}}
                    </h5>
                </div>
            </div>
        @endforeach

        <hr>
        <div class="">
            <div>
                <h5>
                    VATable Sales&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp: {{number_format($sale->vatable, 2)}}
                </h5>
            </div>
            <div>
                <h5>
                    VAT&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp: {{number_format($sale->vat, 2)}}
                </h5>
            </div>
            <div>
                <h5>
                    Non-VAT Sales&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp: {{number_format($sale->non_vat, 2)}}
                </h5>
            </div>
            <div>
                <h5>
                    Zero Rated Sales&nbsp&nbsp: {{number_format($sale->zero_rated, 2)}}
                </h5>
            </div>
            <div>
                <h5>
                    VAT-Exempt&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp: {{number_format($sale->vat_exempt, 2)}}
                </h5>
            </div>
        </div>

        <hr>
        <h4 class="text-right">
            Total Discount&nbsp&nbsp: ₱ {{number_format($sale->discount, 2)}}
        </h4>
        <h2 class="text-right">
            Total: ₱ {{number_format($sale->total, 2)}}
        </h2>
        <hr>
        <h4 class="text-right">
            Cash: ₱ {{number_format($sale->cash, 2)}}
        </h4>
        <h4 class="text-right">
            Change: ₱ {{number_format($sale->cash - $sale->total, 2)}}
        </h4>

        <hr>

        <h4 class="text-center">
            ({{$total}}) item/s
        </h4>

        <hr>
        <div>
            <h5>
                Name&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp:
            </h5>
        </div>
        <div>
            <h5>
                Address&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp:
            </h5>
        </div>
        <div>
            <h5>
                TIN&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp:
            </h5>
        </div>
        <div>
            <h5>
                Business Type&nbsp&nbsp&nbsp:
            </h5>
        </div>
        @if ($sale->sale_type)
            <div>
                <h5>
                    SC/PWD ID&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp: ____________________________________
                </h5>
            </div>
        @endif

        <hr>
        <div class="text-center">
            <h4>
                {{$receipt->name}}
            </h4>
            <h4>
                {{$receipt->header}}
            </h4>
            <h4>
                TIN : {{$receipt->tin}}
            </h4>
            <h4>
                Email : {{$receipt->email}}
            </h4>
            <h4>
                Phone : {{$receipt->phone}}
            </h4>
            <h4>
                PTU # : {{$receipt->ptu}}
            </h4>
            <h4>
                Accreditation # : {{$receipt->accredition}}
            </h4>
        </div>
        <hr>
        <h4 class="text-center">
            {{$sale->message}}
        </h4>
        <hr>
        <h4 class="text-center">
            THIS SERVES AS YOUR OFFICIAL RECEIPT.
        </h4>
        <hr>
        <h4 class="text-center">
            THIS RECEIPT SHALL BE VALID FOR FIVE (5) YEARS FROM THE DATE OF THE PERMIT TO USE.
        </h4>
    </div>
@endsection
