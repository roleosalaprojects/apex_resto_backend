@extends('admin.layouts.master')
@section('title')

@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{route('view.receipts')}}">Receipts</a></li>
    <li class="breadcrumb-item active">SI # : {{$sale->son}}</li>
@endsection
@section('content')
    <div class="row">
        <div class="col-md-6 col-lg-3 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-center">
                        <strong class="text-center">
                            {{$sale->store}}
                        </strong>
                    </div>
                    <div class="d-flex justify-content-center">
                        <strong class="text-center">
                            {{$sale->header}}
                        </strong>
                    </div>
                    <div class="d-flex justify-content-center">
                        <strong class="text-center">
                            Tin: {{$sale->TIN}}
                        </strong>
                    </div>
                    <div class="d-flex justify-content-center">
                        <strong class="text-center">
                            PN: {{$sale->pn}}
                        </strong>
                    </div>
                    <div class="d-flex justify-content-center">
                        <strong class="text-center">
                            Email: {{$sale->email}}
                        </strong>
                    </div>
                    <div class="d-flex justify-content-center">
                        <strong class="text-center">
                            MIN : {{$sale->min}}
                        </strong>
                    </div>
                    <div class="d-flex justify-content-center">
                        <strong class="text-center">
                            Serial #: {{$sale->serial}}
                        </strong>
                    </div>
                    <br>

                    <div class="text-justify">
                        Date: {{$sale->date}}
                    </div>

                    <div class="text-justify">
                        Cashier: {{$sale->sold_by}}
                    </div>

                    <br>

                    <h5 class="text-justify">
                        SI #: {{$sale->son}}
                    </h5>
                    @if ($sale->type == true)
                        <h3 class="text-center text-danger">REFUND</h3>
                    @endif

                    @php
                        $total = 0;
                    @endphp


                    <hr>
                    {{-- {{dd($lines)}} --}}
                    @foreach ($lines as $line)
                        @php
                            $total += $line->qty;
                        @endphp
                        <div>
                            <strong>
                                <a href="{{route('items.show', $line->item_id)}}">{{$line->item}}</a>
                            </strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div class="div">
                                <p>
                                    @if ($receipt->hocus_pocus)
                                        @if ($line->excess_vat)
                                            {{$line->qty}} {{$line->unit}} x
                                            ₱ {{number_format($line->price - (($line->excess_vat - $line->excess_non_vat) / $line->qty), 2)}}
                                        @else
                                            {{$line->qty}} {{$line->unit}} x
                                            ₱ {{number_format($line->price - (($line->excess_non_vat - $line->excess_vat) / $line->qty), 2)}}
                                        @endif
                                    @else
                                        {{$line->qty}} {{$line->unit}} x ₱ {{number_format($line->price, 2)}}
                                    @endif
                                </p>
                            </div>
                            <div class="div">
                                <p>
                                    @if ($receipt->hocus_pocus)
                                        @if ($line->excess_vat)
                                            {{number_format($line->sub_total - ($line->excess_vat - $line->excess_non_vat), 2)}}
                                        @else
                                            {{number_format($line->sub_total - ($line->excess_non_vat - $line->excess_vat), 2)}}
                                        @endif
                                    @else
                                        {{number_format($line->sub_total, 2)}}
                                    @endif

                                </p>
                            </div>
                        </div>
                    @endforeach

                    <hr>
                    <div class="">
                        <div>
                            VATable Sales&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp:
                            ₱ {{number_format($sale->vatable, 2)}}
                        </div>
                        <div>
                            @if ($receipt->hocus_pocus)
                                VAT&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp:
                                ₱ {{number_format($sale->vat - $sale->excess_vat, 2)}}
                            @else
                                VAT&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp:
                                ₱ {{number_format($sale->vat, 2)}}
                            @endif
                        </div>
                        <div>
                            @if ($receipt->hocus_pocus)
                                Non-VAT Sales&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp:
                                ₱ {{number_format($sale->non_vat - $sale->excess_non_vat, 2)}}
                            @else
                                Non-VAT Sales&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp: ₱ {{number_format($sale->non_vat, 2)}}
                            @endif
                        </div>
                        <div>
                            Zero Rated Sales&nbsp&nbsp: ₱ {{number_format($sale->zero_rated, 2)}}
                        </div>
                        <div>
                            VAT-Exempt&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp:
                            ₱ {{number_format($sale->vat_exempt, 2)}}
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-end">
                        Total Discount&nbsp&nbsp: ₱ {{number_format($sale->discount, 2)}}
                    </div>
                    <div class="d-flex justify-content-end">
                        <div>
                            <h5>
                                <strong>
                                    @if ($receipt->hocus_pocus)
                                        @if ($sale->excess_vat)
                                            Total:
                                            ₱ {{number_format($sale->total - $sale->excess_vat - $sale->excess_non_vat, 2)}}
                                        @else
                                            Total:
                                            ₱ {{number_format($sale->total - $sale->excess_non_vat - $sale->excess_vat, 2)}}
                                        @endif
                                    @else
                                        Total: ₱ {{number_format($sale->total, 2)}}
                                    @endif
                                </strong>
                            </h5>
                        </div>

                    </div>
                    <hr>
                    <div class="d-flex justify-content-end">
                        <div>
                            Cash: ₱ {{number_format($sale->cash, 2)}}
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <div>
                            @if ($receipt->hocus_pocus)
                                Change:
                                ₱ {{number_format(($sale->cash - $sale->total) + $sale->excess_vat + $sale->excess_non_vat, 2)}}
                            @else
                                Change: ₱ {{number_format($sale->cash - $sale->total, 2)}}
                            @endif

                        </div>
                    </div>
                    <hr>

                    <h5 class="text-center">
                        ({{$total}}) item/s
                    </h5>

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
                                SC/PWD ID&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp:
                                ____________________________________
                            </h5>
                        </div>
                    @endif

                    <hr>
                    <div class="d-flex justify-content-center">
                        <div><strong>{{$receipt->name}}</strong></div>
                    </div>
                    <div class="d-flex justify-content-center">
                        <div>{{$receipt->header}}</div>
                    </div>
                    <div class="d-flex justify-content-center">
                        <div>TIN : {{$receipt->tin}}</div>
                    </div>
                    <div class="d-flex justify-content-center">
                        <div>Email : {{$receipt->email}}</div>
                    </div>
                    <div class="d-flex justify-content-center">
                        <div>Phone : {{$receipt->phone}}</div>
                    </div>
                    <div class="d-flex justify-content-center">
                        <div>PTU # : {{$receipt->ptu}}</div>
                    </div>
                    <div class="d-flex justify-content-center">
                        <div>Accreditation # : {{$receipt->accredition}}</div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-center">
                        <div><strong>THIS SERVES AS YOUR OFFICIAL RECEIPT.</strong></div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-center">
                        <div><strong>THIS RECEIPT SHALL BE VALID FOR FIVE (5) YEARS</strong></div>
                    </div>
                    <div class="d-flex justify-content-center">
                        <div><strong>FROM THE DATE OF THE PERMIT TO USE.</strong></div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-center">
                        <strong class="text-center">
                            {{$sale->message}}
                        </strong>
                    </div>
                </div>
                {{-- <div class="card-footer">
                    <h4>Profit: {{$sale->profit}}</h4>
                </div> --}}
            </div>
        </div>
    </div>
@endsection
