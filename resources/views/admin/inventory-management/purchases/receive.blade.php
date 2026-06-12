@extends('layout.app')
@section('title')
    Receive Purchase Order
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{route('purchases.index')}}">Purchase Orders</a></li>
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{route('purchases.show', $purchase->id)}}">PO #: {{$purchase->po}} Details</a></li>
    <li class="breadcrumb-item pe-3 text-muted">Receive PO #: {{$purchase->po}}</li>
@endsection
@section('actions')
    <button class="btn btn-sm btn-active-color-success btn-bg-light" onclick="fillAll()">Mark all as received</button>
    <button class="btn btn-sm btn-danger" id="btnReceive">Receive</button>
@endsection
@section('content')
    <div class="row">
        <div class="">
            <div class="card card-flush">
                <div class="card-header">
                    <div class="card-title">
                        Details
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <h3>PO #: {{$purchase->po}}</h3>
                        </div>
                        <div class="col-4">
                            <div class="progress progress-xs">
                                <div class="progress-bar @if($purchase->items - $purchase->received != 0) bg-warning @else bg-primary @endif progress-bar-striped" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: {{($purchase->received) ? ($purchase->received / $purchase->items) * 100 : 0}}%"></div>
                            </div>
                            <small>{{$purchase->received}} of {{$purchase->items}} received</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            @if ($purchase->status == 1)
                                <span class="text-warning">Pending</span>
                            @else
                                @if ($purchase->total - $purchase->received > 0)
                                    <span class="text-warning">Partially Received</span>
                                @else
                                    <span class="text-success">Received</span>
                                @endif
                            @endif
                            
                        </div>
                    </div>
                    
                    <br> 

                    <div class="row">
                        <div class="col-6">
                            <strong>Supplier:</strong>&nbsp{{$purchase->supplier}}
                        </div>
                        <div class="col-6">
                            <strong>Store:</strong>&nbsp{{$purchase->store}}
                        </div>
                    </div>

                    <br>

                    <div class="row">
                        <div class="col-6">
                            <strong>Created by:</strong>&nbsp <span class="text-success">{{$purchase->created}}</span>
                        </div>
                    </div>

                    <br>

                    <form action='{{ route('purchase.receive.now', $purchase->id) }}' method="POST" id="formReceive">
                        @csrf
                        <div class="row">
                            <table class="table table-hover" id="receiveTable">
                                <thead>
                                    <tr class="fw-semibold fs-6 text-gray-800 border-bottom border-gray-200">
                                        <th>Item</th>
                                        <th>Unit</th>
                                        <th>Qty</th>
                                        <th>Received</th>
                                        <th>Receivable</th>
                                        <th>Price</th>
                                        <th>To Receive</th>
                                        <th class="text-center">Update Cost</th>
                                    </tr>
                                </thead>
                                <tbody >
                                    @foreach ($purchase_line as $index => $line)
                                        <tr>
                                            <td>
                                                {!! Form::hidden("item_id[]", $line->item_id, []) !!}
                                                {!! Form::hidden("line_id[]", $line->id, []) !!}
                                                {{$line->item}}
                                            </td>
                                            <td>
                                                {!! Form::hidden("unit_id[]", $line->unit_id, []) !!}
                                                @if ($line->unit == "")
                                                    PCS
                                                @else
                                                    {{$line->unit}}
                                                @endif
                                            </td>
                                            <td>{{$line->qty}}</td>
                                            <td>{{$line->received}}</td>
                                            <td><span class="qty">{{$line->qty - $line->received}}</span></td>
                                            <td>₱ {{number_format($line->cost, 2)}}</td>
                                            <td>
                                                <input name="toReceive[]" type="number" class="form-control toReceive" onkeyup="return isNumberKey(event)" oninput='limitDecimalPlaces(event, 2)' max="{{$line->qty - $line->received}}" min="0" autocomplete="false" required>
                                            </td>
                                            <td class="text-center">
                                                <div class="form-check form-check-custom form-check-sm d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox" name="update_cost[{{ $index }}]" value="1" checked>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('styles')
    <link rel="stylesheet" href="{{ asset('plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
@endsection
@section('scripts')
    
    <script src="{{ asset('plugins/sweetalert2/sweetalert2.min.js') }}"></script>
    <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
    <script>
        var optionals = 0;
        $(document).ready(function(){
            let btnReceive, form, validator;
            handleForm();



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

            // Private functions
            let checker = arr => arr.every(v => v === true);
            function handleForm(){
                form = document.querySelector("#formReceive");
                submitButton = document.querySelector("#btnReceive");
                validator = FormValidation.formValidation(
                    form,
                    {},
                )

                submitButton.addEventListener('click', function(e){
                    e.preventDefault();
                    // Assign Variables to inputs
                    var receivable = $("input[name='toReceive[]']").map(function(){
                        if(!$(this).val() || $(this).val() == null){
                            $(this).addClass('is-invalid');
                            $(this).after("<span class='text-danger qty-field'>Cannot be blank!</span>")
                            return false;
                        }else{
                            $(this).removeClass('is-invalid')
                            $(this).closest("tr").find(".qty-field").remove();
                            return true;
                        }
                    }).get();
                    validator.validate().then(function (status){
                        if(status == 'Valid' && checker(receivable)){
                            Swal.fire({
                                title: "Proceed with caution!",
                                html: `Are you sure you want to receive this <strong>Purchase Order (PO #: {{ $purchase->po }})</strong>? This is not reversible. `,
                                icon: "warning",
                                buttonsStyling: false,
                                showCancelButton: true,
                                confirmButtonText: "Proceed.",
                                cancelButtonText: 'Nope, cancel it.',
                                closeOnConfirm: false,
                                customClass: {
                                    confirmButton: "btn btn-primary",
                                    cancelButton: 'btn btn-danger'
                                }
                            }).then(function(isConfirm){
                                if(isConfirm){
                                    form.submit();
                                }else{
                                    
                                }
                            })
                        }
                    });
                })
            }
        });
        var offset = 0;
        function fillAll(){
            $("#receiveTable tbody tr").each(function() {
                var value = $(this).find(" td:nth-child(5)").text();
                $(this).find(" td:nth-child(7) input").val(value);
            });
        }
        function MaxRceivable(){
            $("#receiveTable tbody tr").each(function() {
                var value = $(this).find(" td:nth-child(3)").text() - $(this).find(" td:nth-child(4)").text();
                if($(this).find(" td:nth-child(6) input").val() > value){
                    $(this).find(" td:nth-child(6) input").addClass("is-invalid")
                    offset++;
                }else{
                    if(offset > 0){
                        offset--;
                    }
                }
                if($(this).find(" td:nth-child(6) input").val() < 0){
                    $(this).find(" td:nth-child(6) input").addClass("is-invalid")
                    offset++;
                }else{
                    if(offset > 0){
                        offset--;
                    }
                }
            });
        }
    </script>
@endsection