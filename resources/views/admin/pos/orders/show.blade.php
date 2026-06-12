@extends('layout.app')
@section('header')
    - Order # {{ $order->id }}
@endsection
@section('title')
    Order #:{{ $order->id }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3 text-muted"><a href="{{ route('orders.index') }}" class="pe-3">Orders Management</a>
    </li>
    <li class="breadcrumb-item pe-3 text-muted">Order #: {{ $order->id }}</li>
@endsection
@section('actions')
    {{--    Pending --}}
    @if($order->status == 0)
        <button class="btn btn-sm btn-success fw-bold" id="btnAcceptOrder">Accept Order</button>
        <button class="btn btn-sm btn-danger fw-bold" id="btnCancelOrder">Cancel Order</button>
    @endif
    @if($order->status == 1)
        <button class="btn btn-sm btn-info fw-bold" data-bs-toggle="modal" data-bs-target="#assignOrderModal">Assign
        </button>
    @endif
    @if($order->status == 2)
        <button class="btn btn-sm btn-success fw-bold" id="btnOrderPrepared">Order Prepared</button>
    @endif
    @if($order->status == 3)
        <button class="btn btn-sm btn-danger fw-bold" id="btnCompleteOrder">Complete Order</button>
    @endif
@endsection
@section('content')
    <div class="row mb-10">
        <div class="col">
            <div class="card card-flush">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-10">
                                <span class="fs-3 fw-bold">Order Details</span>
                            </div>
                            <div class="mb-3">
                                <span class="fs-5 fw-bold">Created By:</span> <span
                                        class="fs-5 font-weight-normal">{{ $order->creator->name }}</span>
                            </div>
                            <div class="mb-3">
                                <span class="fs-5 fw-bold">POS:</span> <span
                                        class="fs-5 font-weight-normal">{{ $order->pos ? $order->pos->name : 'Mobile' }}</span>
                            </div>
                            <div class="mb-3">
                                <span class="fs-5 fw-bold">Order Date:</span> <span
                                        class="fs-5 font-weight-normal">{{ $order->created_at }}</span>
                            </div>
                            <div class="mb-3">
                                <span class="fs-5 fw-bold">Status:</span>
                                <span class="fs-5 font-weight-normal">
                                    @if($order->status == 0)
                                                <span class="badge badge-light-warning">
                                            Pending
                                        </span>
                                            @endif
                                            @if($order->status == 1)
                                                <span class="badge badge-light-primary">
                                            Order Accepted
                                        </span>
                                            @endif
                                            @if($order->status == 2)
                                                <span class="badge badge-light-info">
                                            Preparing
                                        </span>
                                            @endif
                                            @if($order->status == 3)
                                                <span class="badge badge-light-success">
                                            For Pickup
                                        </span>
                                            @endif
                                            @if($order->status == 4)
                                                <span class="badge badge-light-success">
                                            Completed
                                        </span>
                                            @endif
                                            @if($order->status == 5)
                                                <span class="badge badge-light-danger">
                                            Cancelled
                                        </span>
                                            @endif
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            @if($order->status != 5)
                                <div class="row mb-6">
                                    <div class="col-md-6">
                                        <span class="fs-5 fw-bold">Accepted By:</span> <span
                                                class="fs-5 fw-bold text-primary">{{ $order->acceptor ? $order->acceptor->name : 'N/A' }}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="fs-5 fw-bold">Accepted Date:</span> <span
                                                class="fs-5 fw-bold text-primary">{{ $order->acceptor ? $order->accepted_at : 'N/A' }}</span>
                                    </div>
                                </div>
                                <div class="row mb-6">
                                    <div class="col-md-6">
                                        <span class="fs-5 fw-bold">Assigned By:</span> <span
                                                class="fs-5 fw-bold text-warning">{{ $order->assigner ? $order->assigner->name : 'N/A' }}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="fs-5 fw-bold">Assigned Date:</span> <span
                                                class="fs-5 fw-bold text-warning">{{ $order->assigner ? $order->assigned_at : 'N/A' }}</span>
                                    </div>
                                </div>
                                <div class="row mb-6">
                                    <div class="col-md-6">
                                        <span class="fs-5 fw-bold">Prepared By:</span> <span
                                                class="fs-5 fw-bold text-info">{{ $order->preparer ? $order->preparer->name : 'N/A' }}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="fs-5 fw-bold">Preparation Date:</span> <span
                                                class="fs-5 fw-bold text-info">{{ $order->preparer ? $order->prepared_at : 'N/A' }}</span>
                                    </div>
                                </div>
                                <div class="row mb-6">
                                    <div class="col-md-6">
                                        <span class="fs-5 fw-bold">Completed By:</span> <span
                                                class="fs-5 fw-bold text-success">{{ $order->finisher ? $order->finisher->name : 'N/A' }}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="fs-5 fw-bold">Completion Date:</span> <span
                                                class="fs-5 fw-bold text-success">{{ $order->finisher ? $order->completed_at : 'N/A' }}</span>
                                    </div>
                                </div>
                            @else
                                <div class="row mb-6">
                                    <div class="col-md-6">
                                        <span class="fs-5 fw-bold">Cancelled By:</span> <span
                                                class="fs-5 fw-bold text-danger">{{ $order->cancelled ? $order->cancelled->name : 'N/A' }}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="fs-5 fw-bold">Cancelled Date:</span> <span
                                                class="fs-5 fw-bold text-danger">{{ $order->cancelled ? $order->cancelled_at : 'N/A' }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="separator my-10"></div>
                    <div class="row">
                        <div class="col-md-6"></div>
                        <div class="col-md-6">
                            <div class="mb-6">
                                <span class="fs-3 fw-bold">Total Amount</span>
                            </div>
                            <div class="mb-3">
                                <span class="fs-3 fw-bolder">{{ number_format($order->amount, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col">
            <div class="card card-flush">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-300 gy-7">
                            <thead>
                            <tr class="fw-bold fs-6 text-gray-800">
                                <th>Product Name</th>
                                <th>QTY.</th>
                                <th>Unit</th>
                                <th>Price</th>
                                <th>Discount</th>
                                <th>Sub Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($order->lines as $line)
                                <tr>
                                    <td>{{ $line->item_name }}</td>
                                    <td>{{ number_format($line->qty, 2) }}</td>
                                    <td>{{ $line->unit_name }}</td>
                                    <td>{{ number_format($line->price, 2) }}</td>
                                    <td>{{ number_format($line->discount, 2) }}</td>
                                    <td>{{ number_format($line->sub_total, 2) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('modals')
    <div class="modal fade" tabindex="-1" id="assignOrderModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Modal title</h3>

                    <!--begin::Close-->
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal"
                         aria-label="Close">
                        <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                    </div>
                    <!--end::Close-->
                </div>
                <form action="#" class="form w-100" id="assignTerminalForm" novalidate="novalidate">
                    <div class="modal-body">
                        <div class="mb-3 form-group fv-row">
                            <select class="form-select form-select-solid" data-control="select2"
                                    data-dropdown-parent="#assignOrderModal" data-placeholder="Select a Terminal"
                                    data-allow-clear="true" id="terminalSelect" name="terminalSelect">
                                <option></option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" id="btnAssignTerminal" class="btn btn-success">
                            <span class="indicator-label">Continue</span>
                            <span class="indicator-progress">Please wait...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script src="{{asset('assets/js/swal.js')}}"></script>
    <script>
        $(document).ready(function () {
            var btnAcceptOrder = $("#btnAcceptOrder");
            var btnCancelOrder = $("#btnCancelOrder");
            var terminalSelect = $("#terminalSelect");
            var btnOrderPrepared = $("#btnOrderPrepared");
            var btnCompleteOrder = $("#btnCompleteOrder");

            @if ($order->status == 0)
                btnAcceptOrder.on('click', () => {
                    confirmAcceptOrder();
                });
                btnCancelOrder.on('click', () => {
                    confirmCancelOrder();
                })
            @endif
            @if ($order->status == 2)
                btnOrderPrepared.on('click', ()=>{
                    confirmOrderPrepared();
                })
            @endif
            @if ($order->status == 3)
                btnCompleteOrder.on('click', ()=>{
                    completeOrder();
                })
            @endif
            
            @if($order->status == 1)
            //Run this code if order is Accepted
            var btnAssignTerminal = document.querySelector("#btnAssignTerminal");
            var form = document.querySelector("#assignTerminalForm")
            var validator;
            terminalSelect.select2({
                dropdownParent: $("#assignOrderModal"),
                ajax: {
                    url: '{{route('pos.select')}}',
                    method: 'GET',
                    dataType: 'JSON',
                    data: function (params) {
                        var queryParameters = {
                            term: params.term
                        }
                        return queryParameters;
                    },
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    },
                }
            })

            validator = FormValidation.formValidation(
                form,
                {
                    fields: {
                        terminalSelect: {
                            validators: {
                                notEmpty: {
                                    message: 'Terminal Cannot be blank!',
                                }
                            }
                        }
                    }
                }
            );

            btnAssignTerminal.addEventListener('click', (e) => {
                e.preventDefault();
                validator.validate().then((status) => {
                    if (status == 'Valid') {
                        Swal.fire({
                            html: "Are you sure you want to assign <span class='fw-bolder fs-3'>" + $("#terminalSelect").text() + "</span> to Order# <span class='fw-bolder fs-3'>{{$order->reference}}</span>",
                            icon: "warning",
                            buttonsStyling: false,
                            showCancelButton: true,
                            confirmButtonText: "Confirm",
                            cancelButtonText: "Cancel",
                            customClass: {
                                confirmButton: "btn btn-primary",
                                cancelButton: 'btn btn-danger'
                            }
                        }).then(function (result) {
                            if (result.isConfirmed) {
                                sendAssignedTerminal($("#terminalSelect").val());
                            }
                        });
                    } else {
                        Swal.fire({
                            text: 'Please assign a Terminal first.',
                            icon: 'error',
                            buttonsStyling: false,
                            confirmButtonText: 'Ok, got it!',
                            customClass: {
                                confirmButton: "btn btn-primary",
                            }
                        })
                    }
                });
            })

            function sendAssignedTerminal(id) {
                $.ajax({
                    url: '/admin/orders/assign/{{ $order->id }}/' + id,
                    method: 'POST',
                    success: function (response) {
                        console.log(response);
                        if (response.success == true) {
                            location.reload();
                        }
                    },
                    error: function (response) {
                        errorSwal('Error', response.responseJSON.message);
                    }
                })
            }
            @endif

            @if ($order->status == 0)
                function confirmAcceptOrder() {
                    fireSwal(
                        "Are you sure you want to accept this Order# <span class='fw-bolder fs-3'>{{$order->reference}}</span>",
                        "info",
                        function (result) {
                            if (result.isConfirmed) {
                                sendRequest(1);
                            }
                        }
                    )
                }

                function confirmCancelOrder() {
                    fireSwal(
                        "Are you sure you want to cancel this Order# <span class='fw-bolder fs-3'>{{$order->reference}}</span>",
                        "warning",
                        function (result) {
                            if (result.isConfirmed) {
                                sendRequest(0);
                            }
                        }
                    )
                }
            @endif
            @if ($order->status == 2)
                function confirmOrderPrepared(){
                    fireSwal(
                        "Are you sure you want to set the status of this Order# <span class='fw-bolder fs-3'>{{$order->reference}} </span> to <span class='fw-bolder fs-3 text-info'>Order Prepared?</span>",
                        "warning",
                        function (result) {
                            if (result.isConfirmed) {
                                $.ajax({
                                    url: '{{route('orders.prepared', $order->id)}}',
                                    type: 'PUT',
                                    success: function(response){
                                        console.log(response);
                                        location.reload();
                                    },
                                    error: function (response){
                                        console.log(response);
                                    }
                                })
                            }
                        }
                    )
                }
            @endif
            @if ($order->status == 3)
                function completeOrder(){
                    fireSwal(
                        "Are you sure you want to set the status of this Order# <span class='fw-bolder fs-3'>{{$order->reference}} </span> to <span class='fw-bolder fs-3 text-danger'>Order Completed and Picked Up?</span>",
                        "warning",
                        function(result){
                            if(result.isConfirmed){
                                $.ajax({
                                    url: '{{route('orders.complete', $order->id)}}',
                                    method: 'PUT',
                                    success: function(response){
                                        if(response.success == true){
                                            location.reload();
                                        }
                                    },
                                    error: function(response){
                                        console.log(response);
                                    }
                                })
                            }
                        }
                    )
                }
            @endif

            

            function fireSwal(html, icon, result){
                Swal.fire({
                    html: html,
                    icon: icon,
                    buttonsStyling: false,
                    showCancelButton: true,
                    confirmButtonText: "Confirm",
                    cancelButtonText: "Cancel",
                    customClass: {
                        confirmButton: "btn btn-primary",
                        cancelButton: 'btn btn-danger'
                    }
                }).then(result);
            }

            function sendRequest(confirmed, data) {
                // confirmed variable must only be true or false. 1 = Accepted, 0 = Cancel.
                $.ajax({
                    type: 'POST',
                    url: '{{route('orders.confirmed', $order->id)}}',
                    data: {
                        status: confirmed  // Confirmed must only be true or false
                    },
                    success: function(response){
                        location.reload();
                    },
                    error: function(response){
                        errorSwal('Error', response.responseJSON.message);
                    }
                })
            }
        });
    </script>
@endsection