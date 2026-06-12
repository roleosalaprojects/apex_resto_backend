@extends('admin.printer.default')
@section('style')
    <link href='https://fonts.googleapis.com/css?family=Libre Barcode 39' rel='stylesheet'>
    <style>
        .item-barcode {
            font-size: 15px;
        }

        .item-price {
            font-size: 55px;
        }

        .item-name {
            font-size: 25px;
            word-wrap: break-word;
        }

        .item-code39 {
            font-family: 'Libre Barcode 39';
            font-size: 22px;
            /* font-weight: 500;
            font-stretch: condensed; */
        }

        .item-label {
            width: 500px;
        }
    </style>
@endsection
@section('content')
    @foreach ($items as $item)
        <div class="col invoice-col">
            <div class="item-label">
                <h5><span class="item-name">{{$item['description']}}({{$item['unit']}})</span></h5>
                <h3><span class="item-price">₱{{number_format($item['price'], 2)}}</span></h3>
            </div>
            {{-- <address>
                <div class="item-code39">
                    <h5>{{$item['barcode']}}</h5>
                </div>
            </address> --}}
        </div>
        <br>
        {{-- <div class="row">
            <div class="col-12">
                <div class="item-name text-center">
                    {{$item['description']}}({{$item['unit']}})
                </div>
                <div class="item-price text-center">
                    ₱{{number_format($item['price'], 2)}}
                </div>
                <div class="item-code39 text-center">
                    {{$item['barcode']}}
                </div>
                <br>
                <br>
            </div>
        </div> --}}
    @endforeach

@endsection
