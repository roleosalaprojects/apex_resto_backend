@extends('layout.app')
@section('header')
    - View Reading
@endsection
@section('title')
    {{ Str::upper($type) }} - Reading: {{ $reading->counter }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted"><span class="">Reports</span></li>
    <li class="breadcrumb-item"><a class="pe3=-3" href="{{ route('reports.readings') }}">Readings</a></li>
    <li class="breadcrumb-item text-muted">View Reading</li>
@endsection
@section('actions')
    
@endsection
@section('content')
    <div class="row">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-7">
                    <div>
                        <div class="text-sm-start">
                            <span class="fs-2 fw-bolder">{{ Str::upper($type) }} - Reading</span>
                        </div>
                        <br>
                        <span class="fs-5 fw-semibold text-muted">
                            Reporting Date: {{$reading->created_at}}
                        </span>
                        <br>
                        <span class="fs-5 fw-semibold text-muted">
                            Terminal No. : {{$reading->pos->number}}
                        </span>
                        <br>
                        <span class="fs-5 fw-semibold text-muted">
                            Counter : {{$reading->counter}}
                        </span>
                        <br>
                        <span class="fs-5 fw-semibold text-muted">
                            Cashier : {{$reading->gen->name}}
                        </span>
                    </div>
                    <div>
                        <div class="text-sm-end">
                            <span class="fs-2 fw-bolder">{{ $reading->store->name }}</span>
                        </div>
                        <br>
                        <span class="fs-5 fw-semibold text-muted">{{ $reading->store->header }}</span>
                        <br>
                        <span class="fs-5 fw-semibold text-muted">TIN: {{ $reading->store->tin }}</span>
                        <br>
                        <span class="fs-5 fw-semibold text-muted">{{ $reading->store->email }}</span>
                        <br>
                        <span class="fs-5 fw-semibold text-muted">MIN: {{ $reading->pos->min }}</span>
                        <br>
                        <span class="fs-5 fw-semibold text-muted">Serial: {{ $reading->pos->serial }}</span>
                    </div>
                </div>
                <div class="separator my-5"></div>
                <div class="row">
                    <div class="col-md-5">
                        <div class="row">
                            <div class="fs-3 fw-bolder mb-5">
                                Tender Reconciliation
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Cash</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format(($reading->cash) ? $reading->cash : 0, 2)}}</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Refund</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format(($reading->cash) ? $reading->refunds : 0, 2)}}</div>
                                </div>
                            </div>
                            <div class="separator mb-3"></div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Gross Sales</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format((($reading->cash) ? $reading->cash : 0) + (($reading->refunds) ? $reading->refunds : 0), 2)}}</div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="fs-3 fw-bolder mt-5 mb-5">
                                VAT Declaration
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">VATable Sales</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format($reading->vatable, 2)}}</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">VAT Amount</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format($reading->vat, 2)}}</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">VAT Exempt Sales</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format($reading->non_vat + $reading->vat_exempt, 2)}}</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Zero Rated</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format($reading->zero_rated, 2)}}</div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="fs-3 fw-bolder mt-5 mb-5">
                                Cashier Accountability
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Current Sales</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format(($reading->cash) ? $reading->cash : 0, 2)}}</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Less: Refunds</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format($reading->less_refunds, 2)}}</div>
                                </div>
                            </div>
                            <div class="separator mb-3"></div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Net Sales</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format((($reading->cash) ? $reading->cash : 0) - (($reading->refunds) ? $reading->refunds : 0), 2)}}</div>
                                </div>
                            </div>
                            <div class="separator mb-3"></div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Total Cash In</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format($reading->cash_in, 2)}}</div>
                                </div>
                            </div>
                            <div class="separator mb-3"></div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Total in Drawer</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format(((($reading->cash) ? $reading->cash : 0) - (($reading->refunds) ? $reading->refunds : 0)) + $reading->cash_in, 2)}}</div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="fs-3 fw-bolder mt-5 mb-5">
                                Cashier Audit
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Transactions</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format($reading->transactions, 2)}}</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">SC Discounts</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format($reading->sc_discounts, 2)}}</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">PWD Discounts</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format($reading->pwd_discounts, 2)}}</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Regular Discounts</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format($reading->reg_discounts, 2)}}</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Regular Discounts</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format($reading->zero_rated, 2)}}</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Net Sales</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format((($reading->cash) ? $reading->cash : 0) - (($reading->refunds) ? $reading->refunds : 0), 2)}}</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Sales Refunds</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format($reading->refunds, 2)}}</div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="fs-3 fw-bolder mt-5 mb-10">
                                Counter
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Sales Refunds</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format($reading->refunds, 2)}}</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Transactions</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{number_format($reading->transaction, 2)}}</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">First Official Receipt<</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{ $reading->first_or }}</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Last Official Receipt</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">{{ $reading->last_or }}</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-sm-start">
                                    <div class="fs-5 fw-bold">Reset Counter</div>
                                </div>
                                <div class="text-sm-end">
                                    <div class="fs-5 fw-bold">N/A</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2"></div>
                    <div class="col-md-5">
                        <div class="fs-3 fw-bolder mb-5 ">
                            Cash Denomination
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bold">₱ 1000 x {{($reading->one_thousand) ? $reading->one_thousand : 0}}</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format($reading->one_thousand * 1000, 2)}}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bold">₱ 500 x {{($reading->five_hundred) ? $reading->five_hundred : 0}}</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format($reading->five_hundred * 500, 2)}}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bold">₱ 200 x {{($reading->two_hundred) ? $reading->two_hundred : 0}}</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format($reading->two_hundred * 200, 2)}}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bold">₱ 100 x {{($reading->one_hundred) ? $reading->one_hundred : 0}}</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format($reading->one_hundred * 100, 2)}}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bold">₱ 50 x {{($reading->fifty) ? $reading->fifty : 0}}</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format($reading->fifty * 50, 2)}}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bold">₱ 20 x {{($reading->twenty) ? $reading->twenty : 0}}</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format($reading->twenty * 20, 2)}}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bold">₱ 10 x {{($reading->ten) ? $reading->ten : 0}}</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format($reading->ten * 10, 2)}}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bold">₱ 5 x {{($reading->five) ? $reading->five : 0}}</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format($reading->five * 5, 2)}}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bold">₱ 1 x {{($reading->one) ? $reading->one : 0}}</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format($reading->one * 1, 2)}}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bold">₱ 0.50 x {{($reading->fifty_cents) ? $reading->fifty_cents : 0}}</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format($reading->fifty_cents * 0.50, 2)}}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bold">₱ 0.25 x {{($reading->twenty_cents) ? $reading->twenty_cents : 0}}</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format($reading->twenty_cents * 0.20, 2)}}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bold">₱ 0.10 x {{($reading->ten_cents) ? $reading->ten_cents : 0}}</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format($reading->ten_cents * 0.10, 2)}}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bold">₱ 0.05 x {{($reading->five_cents) ? $reading->five_cents : 0}}</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format($reading->five_cents * 0.05, 2)}}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bold">₱ 0.01 x {{($reading->one_cents) ? $reading->one_cents : 0}}</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format($reading->one_cents * 0.01, 2)}}</div>
                            </div>
                        </div>
                        <div class="my-10 separator"></div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bolder">Total</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format($reading->one_cents * 0.01, 2)}}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bolder">Net Sales</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format((($reading->cash) ? $reading->cash : 0) - (($reading->refunds) ? $reading->refunds : 0), 2)}}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-sm-start">
                                <div class="fs-5 fw-bolder">Discrepancy</div>
                            </div>
                            <div class="text-sm-end">
                                <div class="fs-5 fw-bold">₱ {{number_format($reading->discrepancy, 2)}}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('styles')
    
@endsection