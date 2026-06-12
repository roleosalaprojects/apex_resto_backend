@extends('admin.layouts.master')
@section('title')
    Payment for PO#: {{$purchase->po}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{route('purchases.index')}}">Purchase Orders</a></li>
    <li class="breadcrumb-item text-muted">Payment for PO#: {{$purchase->po}}</li>
@endsection
@section('content')
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>
                        Payment Details
                    </h3>
                </div>
                {!! Form::open(['route'=>['purchase.save.payment', $purchase->id]]) !!}
                <div class="card-body">
                    <h4>Total Amount to Pay: ₱ <span class="text-danger">{{$purchase->total}}</span></h4>

                    <div class="form-group">
                        {!! Form::label("payment_type", "Payment Type", []) !!}
                        {!! Form::select("payment_type", ['Cash', 'Cheque'], "", ["class"=>'form-control  '.($errors->has('payment_type') ? 'is-invalid' : '')]) !!}
                        @error('payment_type')
                        <span class="text-danger">{{$message}}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        {!! Form::label("purchased", "Date Issued", []) !!}
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                                </div>
                                <input name="date_issued" type="text"
                                       class="form-control {{($errors->has('date_issued') ? ' is-invalid' : '')}}"
                                       data-inputmask-alias="datetime" data-inputmask-inputformat="mm/dd/yyyy"
                                       data-mask="" im-insert="false">
                            </div>
                            <!-- /.input group -->
                        </div>
                        <span class="text-danger">{{$errors->has('purchased') ? "Purchase Date field cannot be empty!" : ''}}</span>
                        @error('date_issued')
                        <span class="text-danger">{{$message}}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        {!! Form::label("cheque_no", "Cheque No.", []) !!}
                        {!! Form::text("cheque_no", "", ["class"=>'form-control  '.($errors->has('cheque_no') ? 'is-invalid' : ''), 'disabled', 'id'=>'cheque_no']) !!}
                        @error('cheque_no')
                        <span class="text-danger">{{$message}}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        {!! Form::label("issued_to", "Issued to", []) !!}
                        {!! Form::text("issued_to", "", ["class"=>'form-control  '.($errors->has('issued_to') ? 'is-invalid' : ''), 'disabled', 'id'=>'issued_to']) !!}
                        @error('issued_to')
                        <span class="text-danger">{{$message}}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        {!! Form::label("issued_by", "Issued By", []) !!}
                        {!! Form::text("issued_by", "", ["class"=>'form-control  '.($errors->has('issued_by') ? 'is-invalid' : ''), 'disabled', 'id'=>'issued_by']) !!}
                        @error('issued_by')
                        <span class="text-danger">{{$message}}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        {!! Form::label("amount", "Amount", []) !!}
                        {!! Form::text("", $purchase->total, ["class"=>'form-control  '.($errors->has('amount') ? 'is-invalid' : ''), 'disabled']) !!}
                        @error('amount')
                        <span class="text-danger">{{$message}}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        {!! Form::hidden("amount", $purchase->total, []) !!}
                    </div>


                </div>
                <div class="card-footer">
                    <button class="btn btn-success btn-lg float-right">Pay</button>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-8">
                            <h3>Purchase Order Details</h3>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <h3>PO #: {{$purchase->po}}</h3>
                        </div>
                        <div class="col-4">
                            <div class="progress progress-xs">
                                <div class="progress-bar @if($purchase->items - $purchase->received != 0) bg-warning @else bg-primary @endif progress-bar-striped"
                                     role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"
                                     style="width: {{($purchase->received) ? ($purchase->received / $purchase->items) * 100 : 0}}%"></div>
                            </div>
                            <small>{{$purchase->received}} of {{$purchase->items}} received</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            @if ($purchase->status == 1)
                                <span class="text-warning">Pending</span>
                            @else
                                @if ($purchase->items - $purchase->received > 0)
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
                        <div class="col-6">
                            <strong>Received By: <span class="text-info">{{$purchase->receiver}}</span></strong>
                        </div>
                    </div>

                    <br>

                    <div class="row">
                        <table class="table table-hover">
                            <thead>
                            <th>Item</th>
                            <th>Unit</th>
                            <th>Qty</th>
                            <th>Received</th>
                            <th>Price</th>
                            <th>Sub Total</th>
                            </thead>
                            <tbody>
                            @foreach ($purchase_line as $line)
                                <tr>
                                    <td>
                                        <a href="{{route('items.show', $line->item_id)}}">{{$line->item}}</a>
                                    </td>
                                    <td>
                                        @if ($line->unit == "")
                                            PCS
                                        @else
                                            {{$line->unit}}
                                        @endif
                                    </td>
                                    <td>{{$line->qty}}</td>
                                    <td>{{$line->received}}</td>
                                    <td>₱ {{number_format($line->cost, 2)}}</td>
                                    <td>₱ {{number_format($line->qty * $line->cost, 2)}}</td>
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
@section('style')
    <link rel="stylesheet" href="{{ asset('plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
@endsection
@section('script')
    <script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
    <script src="{{ asset('plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
    <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
    <script src="{{ asset('plugins/sweetalert2/sweetalert2.min.js') }}"></script>
    
    <script>
        $(document).ready(function () {
            $('[data-mask]').inputmask()
            $('#datemask').inputmask('dd/mm/yyyy'), {'placeholder': 'dd/mm/yyyy'}
            $("#payment_type").on('change', function () {
                $val = $(this).val();
                console.log($(this).val());
                if ($val == 0) {
                    // console.log('niagi diri');
                    $('#cheque_no').prop('disabled', true);
                    $('#issued_to').prop('disabled', true);
                    $('#issued_by').prop('disabled', true);
                } else if ($val == 1) {
                    $('#cheque_no').prop('disabled', false);
                    $('#issued_to').prop('disabled', false);
                    $('#issued_by').prop('disabled', false);
                }
            });
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 6000
            });
        });
        $(function () {
            //Date range picker
            $('#reservation').daterangepicker()

        })
        var optionals = 0;

        function isNumberKey(evt) {
            var charCode = (evt.which) ? evt.which : evt.keyCode;
            if (charCode != 26 && charCode > 31
                && (charCode < 28 || charCode > 57))
                return false;

            return true;
        }
    </script>
    <script type="text/javascript">
        $.ajaxSetup({headers: {'csrftoken': '{{ csrf_token() }}'}});
    </script>
@endsection
