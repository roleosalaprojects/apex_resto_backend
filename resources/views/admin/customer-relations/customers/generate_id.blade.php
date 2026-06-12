@extends('admin.printer.default')
@section('title')
    Customer ID | {{$customer->name}}
@endsection
@section('style')
    <style>
        .customer_name {
            position: absolute;
            top: 100px;
            left: px;
        }

        .card {
            height: 2.83in;
            width: 4.38in;
        }

        .centered {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .id_container {
            position: absolute;
            text-align: center;
            color: black;
        }

        .customer-image {
            position: absolute;
            top: 42%;
            left: 15%;
            transform: translate(-50%, -50%);
            width: 80px;
            height: 80px;
        }

        .customer-code {
            position: absolute;
            top: 68%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .card-border {
            border: 1px solid #555;
        }

        .emergency {
            font-size: 14px;
        }

        .e-name {
            position: absolute;
            top: 55%;
            left: 10%;
        }

        .e-phone {
            position: absolute;
            top: 60%;
            left: 10%;
        }

        .e-address {
            position: absolute;
            top: 65%;
            left: 10%;
        }

        .card-validity {
            position: absolute;
            top: 79%;
            left: 20%;
            font-size: 14px;
        }
    </style>
@endsection
@section('custom')
    <div class="row">
        <div class="col-6">
            <div class="id_container">
                <img src="{{asset('/img/customers/customer_id/front.png')}}" alt="front" class="card card-border">
                <div class="centered">
                    <h4><b>{{$customer->name}}</b></h4>
                </div>
                <img src="{{($customer->image) ? asset($customer->image) : asset("dist/img/user2.png") }}" alt="image"
                     class="img-circle customer-image">
            </div>
        </div>
        <div class="col-6">
            <div class="id_container">
                <img src="{{asset('/img/customers/customer_id/back.png')}}" alt="front" class="card card-border">
                @if ($customer->e_name)
                    <div class="e-name emergency">
                        <p>Name: {{$customer->e_name}}</p>
                    </div>
                @endif
                @if ($customer->e_phone)
                    <div class="e-phone emergency">
                        <p>Phone: {{$customer->e_phone}}</p>
                    </div>
                @endif
                @if ($customer->e_address)
                    <div class="e-address emergency">
                        <p>Address: {{$customer->e_address}}</p>
                    </div>
                @endif
                <div class="card-validity">
                    <p>{{Carbon\Carbon::parse($customer->created_at)->addYears(1)->format('d/M/Y')}}</p>
                </div>
            </div>
        </div>
    </div>

@endsection
<html>

</html>
