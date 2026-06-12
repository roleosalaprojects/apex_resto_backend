@extends('admin.layouts.master')
@section('title')

@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{route('view.receipts')}}">Receipts</a></li>
    {{-- <li class="breadcrumb-item active">SI # : {{number_format($reading->son, 2)}}</li> --}}
@endsection
@section('content')
    @php
        $separator = "------------------------------";
    @endphp
    <div class="row">
        <div class="col-lg-5 col-xl-4">
            <div class="card">
                <div class="card-body">
                    {{-- Begin Header --}}
                    <div class="text-center">
                        <b>
                            {{$reading->store->name}}
                        </b>
                    </div>
                    <div class="text-center">
                        <b>
                            {{$reading->store->header}}
                        </b>
                    </div>
                    <div class="text-center">
                        <b>
                            Tin: {{$reading->store->tin}}
                        </b>
                    </div>
                    <div class="text-center">
                        <b>
                            PN: {{$reading->store->phone}}
                        </b>
                    </div>
                    <div class="text-center">
                        <b>
                            Email: {{$reading->store->email}}
                        </b>
                    </div>
                    <div class="text-center">
                        <b>
                            MIN : {{$reading->pos->min}}
                        </b>
                    </div>
                    <div class="text-center">
                        <b>
                            Serial #: {{$reading->pos->serial}}
                        </b>
                    </div>

                    <hr>

                    @if ($type == "x")
                        <div class="text-center">
                            <b>
                                X-Reading
                            </b>
                        </div>
                    @else
                        <div class="text-center">
                            <b>
                                Z-Reading
                            </b>
                        </div>
                    @endif

                    <div class="text-center">
                        Reporting Date: {{$reading->created_at}}
                    </div>
                    <div class="text-center">
                        Terminal No. : {{$reading->pos->number}}
                    </div>
                    <div class="text-center">
                        Counter : {{$reading->counter}}
                    </div>
                    {{-- End Header --}}
                    <hr>
                    {{-- Begin Body --}}
                    <div>
                        {{-- Begin Tender --}}
                        <div class="text-left">
                            <b>
                                TENDER RECONCILIATION
                            </b>
                        </div>
                        <br>
                        <div class="d-flex justify-content-between">
                            <div>
                                Cash
                            </div>
                            <div>
                                @if ($receipt->hocus_pocus)
                                    @if ($reading->excess_vat)
                                        {{number_format(($reading->cash) ? $reading->cash - $reading->excess_vat - $reading->excess_non_vat: 0, 2)}}
                                    @else
                                        {{number_format(($reading->cash) ? $reading->cash - $reading->excess_vat - $reading->excess_non_vat: 0, 2)}}
                                    @endif
                                @else
                                    {{number_format(($reading->cash) ? $reading->cash : 0, 2)}}
                                @endif
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>
                                Refunds
                            </div>
                            <div>
                                {{number_format(($reading->refunds) ? $reading->refunds : 0, 2)}}
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <div>
                                {{$separator}}
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>
                                GROSS SALES
                            </div>
                            <div>
                                @if ($receipt->hocus_pocus)
                                    @if ($reading->excess_vat)
                                        {{number_format((($reading->cash) ? ($reading->cash - $reading->excess_vat - $reading->excess_non_vat): 0) + (($reading->refunds) ? $reading->refunds : 0), 2)}}
                                    @else
                                        {{number_format((($reading->cash) ? ($reading->cash - $reading->excess_non_vat - $reading->excess_vat): 0) + (($reading->refunds) ? $reading->refunds : 0), 2)}}
                                    @endif
                                @else
                                    {{number_format((($reading->cash) ? $reading->cash : 0) + (($reading->refunds) ? $reading->refunds : 0), 2)}}
                                @endif
                            </div>
                        </div>
                        {{-- End Tender --}}
                        <hr>
                        {{-- Begin VAT --}}
                        <div class="text-left">
                            <b>
                                VAT DECLARATION
                            </b>
                        </div>
                        <br>
                        <div class="d-flex justify-content-between">
                            <div>
                                VATabale Sales
                            </div>
                            <div>
                                {{number_format($reading->vatable, 2)}}
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>
                                VAT Amount
                            </div>
                            <div>
                                @if ($receipt->hocus_pocus)
                                    {{number_format($reading->vat - $reading->excess_vat, 2)}}
                                @else
                                    {{number_format($reading->vat, 2)}}
                                @endif
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>
                                Non-VAT
                            </div>
                            <div>
                                @if ($receipt->hocus_pocus)
                                    {{number_format($reading->non_vat - $reading->excess_non_vat, 2)}}
                                @else
                                    {{number_format($reading->non_vat, 2)}}
                                @endif
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>
                                VAT Exempt Sales
                            </div>
                            <div>
                                {{number_format($reading->vat_exempt, 2)}}
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>
                                Zero Rated Sales
                            </div>
                            <div>
                                {{number_format($reading->zero_rated, 2)}}
                            </div>
                        </div>
                        {{-- End VAT --}}
                        <hr>
                        {{-- Begin Accountability --}}
                        <div>
                            <b>
                                CASHIER ACCOUNTABILITY
                            </b>
                        </div>
                        <br>
                        <div class="d-flex justify-content-between">
                            <div>
                                Current Sales
                            </div>
                            <div>
                                @if ($receipt->hocus_pocus)
                                    @if ($reading->excess_vat)
                                        {{number_format(($reading->cash) ? $reading->cash - $reading->excess_vat - $reading->excess_non_vat: 0, 2)}}
                                    @else
                                        {{number_format(($reading->cash) ? $reading->cash - $reading->excess_vat - $reading->excess_non_vat: 0, 2)}}
                                    @endif
                                @else
                                    {{number_format(($reading->cash) ? $reading->cash : 0, 2)}}
                                @endif
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>
                                Less: Refunds
                            </div>
                            <div>
                                {{number_format($reading->less_refunds, 2)}}
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            {{$separator}}
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>
                                TOTAL NET SALES
                            </div>
                            <div>
                                @if ($receipt->hocus_pocus)
                                    @if ($reading->excess_vat)
                                        {{number_format((($reading->cash) ? ($reading->cash - $reading->excess_vat - $reading->excess_non_vat): 0) - (($reading->refunds) ? $reading->refunds : 0), 2)}}
                                    @else
                                        {{number_format((($reading->cash) ? ($reading->cash - $reading->excess_non_vat - $reading->excess_vat): 0) - (($reading->refunds) ? $reading->refunds : 0), 2)}}
                                    @endif
                                @else
                                    {{number_format((($reading->cash) ? $reading->cash : 0) - (($reading->refunds) ? $reading->refunds : 0), 2)}}
                                @endif
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>
                                TOTAL CASH IN
                            </div>
                            <div>
                                {{number_format($reading->cash_in, 2)}}
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            {{$separator}}
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>
                                Total in Drawer
                            </div>
                            <div>
                                @if ($receipt->hocus_pocus)
                                    @if ($reading->excess_vat)
                                        {{number_format(((($reading->cash) ? ($reading->cash - $reading->excess_vat - $reading->excess_non_vat): 0) - (($reading->refunds) ? $reading->refunds : 0)) + $reading->cash_in, 2)}}
                                    @else
                                        {{number_format(((($reading->cash) ? ($reading->cash - $reading->excess_non_vat - $reading->excess_vat): 0) - (($reading->refunds) ? $reading->refunds : 0)) + $reading->cash_in, 2)}}
                                    @endif
                                @else
                                    {{number_format(((($reading->cash) ? $reading->cash : 0) - (($reading->refunds) ? $reading->refunds : 0)) + $reading->cash_in, 2)}}
                                @endif
                            </div>
                        </div>
                        {{-- End Accountability --}}
                        <hr>
                        {{-- Begin Audit --}}
                        <div class="text-left">
                            <b>
                                CASHIER AUDIT
                            </b>
                        </div>
                        <br>
                        <div class="d-flex justify-content-between">
                            <div>Transactions</div>
                            <div>{{number_format($reading->transaction, 2)}}</div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>SC Discounts</div>
                            <div>{{number_format($reading->sc_discounts, 2)}}</div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>PWD Discounts</div>
                            <div>{{number_format($reading->pwd_discounts, 2)}}</div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>Regular Discounts</div>
                            <div>{{number_format($reading->reg_discounts, 2)}}</div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>Zero Rated</div>
                            <div>{{number_format($reading->zero_rated, 2)}}</div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>Net Sales</div>
                            <div>
                                @if ($receipt->hocus_pocus)
                                    @if ($reading->excess_vat)
                                        {{number_format((($reading->cash) ? ($reading->cash - $reading->excess_vat - $reading->excess_non_vat): 0) - (($reading->refunds) ? $reading->refunds : 0), 2)}}
                                    @else
                                        {{number_format((($reading->cash) ? ($reading->cash - $reading->excess_non_vat - $reading->excess_vat): 0) - (($reading->refunds) ? $reading->refunds : 0), 2)}}
                                    @endif
                                @else
                                    {{number_format((($reading->cash) ? $reading->cash : 0) - (($reading->refunds) ? $reading->refunds : 0), 2)}}
                                @endif
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>Sales Refunds</div>
                            <div>{{number_format($reading->refunds, 2)}}</div>
                        </div>
                        {{-- End Audit --}}
                        <hr>
                        {{-- Begin Counters --}}
                        <div class="text-left">
                            <b>
                                COUNTERS
                            </b>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>Sales Refunds</div>
                            <div>{{number_format($reading->refunds, 2)}}</div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>Transactions</div>
                            <div>{{number_format($reading->transaction, 2)}}</div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>First Official Receipt</div>
                            <div>{{$reading->for}}</div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>Last Official Receipt</div>
                            <div>{{$reading->lor}}</div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>Reset Counter</div>
                            <div>{{number_format(0, 2)}}</div>
                        </div>
                        {{-- End Counter --}}
                        <hr>
                        {{-- Begin Cashier --}}
                        <div class="text-left">
                            <b>Cashier : {{$reading->gen->name}}</b>
                        </div>
                        {{-- End Cashier --}}
                        {{-- Begin Date Generated --}}
                        <div class="text-left">
                            <b>Date Generated : {{$reading->created_at}}</b>
                        </div>
                        {{-- End Date Generated --}}
                        <hr>
                        {{-- Begin Cash Denomination --}}
                        @if ($type == "z")
                            <div class="text-left">
                                <b>Cash Denomination</b>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>1000 x {{($reading->one_thousand) ? $reading->one_thousand : 0}}</div>
                                <div>=&nbsp{{number_format($reading->one_thousand * 1000, 2)}}</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>500 x {{($reading->five_hundred) ? $reading->five_hundred : 0}}</div>
                                <div>=&nbsp{{number_format($reading->five_hundred * 500, 2)}}</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>200 x {{($reading->two_hundred) ? $reading->two_hundred : 0}}</div>
                                <div>=&nbsp{{number_format($reading->two_hundred * 200, 2)}}</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>100 x {{($reading->one_hundred) ? $reading->one_hundred : 0}}</div>
                                <div>=&nbsp{{number_format($reading->one_hundred * 100, 2)}}</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>50 x {{($reading->fifty) ? $reading->fifty : 0}}</div>
                                <div>=&nbsp{{number_format($reading->fifty * 50, 2)}}</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>20 x {{($reading->twenty) ? $reading->twenty : 0}}</div>
                                <div>=&nbsp{{number_format($reading->twenty * 20, 2)}}</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>10 x {{($reading->ten) ? $reading->ten : 0}}</div>
                                <div>=&nbsp{{number_format($reading->ten * 10, 2)}}</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>5 x {{($reading->five) ? $reading->five : 0}}</div>
                                <div>=&nbsp{{number_format($reading->five * 5, 2)}}</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>1 x {{($reading->one) ? $reading->one : 0}}</div>
                                <div>=&nbsp{{number_format($reading->one * 1, 2)}}</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>0.50 x {{($reading->fifty_cents) ? $reading->fifty_cents : 0}}</div>
                                <div>=&nbsp{{number_format($reading->fifty_cents * 0.50, 2)}}</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>0.25 x {{($reading->twenty_cents) ? $reading->twenty_cents : 0}}</div>
                                <div>=&nbsp{{number_format($reading->twenty_cents * 0.20, 2)}}</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>0.10 x {{($reading->ten_cents) ? $reading->ten_cents : 0}}</div>
                                <div>=&nbsp{{number_format($reading->ten_cents * 0.10, 2)}}</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>0.05 x {{($reading->five_cents) ? $reading->five_cents : 0}}</div>
                                <div>=&nbsp{{number_format($reading->five_cents * 0.05, 2)}}</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>0.1 x {{($reading->one_cents) ? $reading->one_cents : 0}}</div>
                                <div>=&nbsp{{number_format($reading->one_cents * 0.01, 2)}}</div>
                            </div>
                            <div class="text-right">
                                {{$separator}}
                            </div>
                            <div class="d-flex justify-content-between">
                                <div><b>TOTAL&nbsp:</b></div>
                                <div><b>{{number_format($reading->total_amount, 2)}}</b></div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div>
                                    <b>
                                        CASH-IN&nbsp:
                                    </b>
                                </div>
                                <div>
                                    <b>{{number_format($reading->cash_in, 2)}}</b>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div><b>NET&nbspSALES&nbsp:</b></div>
                                <div>
                                    <b>
                                        @if ($receipt->hocus_pocus)
                                            @if ($reading->excess_vat)
                                                {{number_format((($reading->cash) ? ($reading->cash - $reading->excess_vat - $reading->excess_non_vat): 0) - (($reading->refunds) ? $reading->refunds : 0), 2)}}
                                            @else
                                                {{number_format((($reading->cash) ? ($reading->cash - $reading->excess_non_vat - $reading->excess_vat): 0) - (($reading->refunds) ? $reading->refunds : 0), 2)}}
                                            @endif
                                        @else
                                            {{number_format((($reading->cash) ? $reading->cash : 0) - (($reading->refunds) ? $reading->refunds : 0), 2)}}
                                        @endif
                                    </b>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div><b>DISCREPANCY&nbsp:</b></div>
                                <div>
                                    <b>
                                        @if ($receipt->hocus_pocus)
                                            {{number_format($reading->discrepancy + $reading->excess_vat + $reading->excess_non_vat, 2)}}
                                        @else
                                            {{number_format($reading->discrepancy, 2)}}
                                        @endif
                                    </b>
                                </div>
                            </div>
                        @else

                        @endif
                        {{-- End Cash Denomination --}}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
