@extends('layout.app')
@section('header')
    - Edit Discount
@endsection
@section('title')
    Edit Discount: {{$discount->name}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{route('discounts.index')}}">Discounts</a></li>
    <li class="breadcrumb-item active">Edit Discount : {{$discount->name}}</li>
@endsection
@section('actions')
    {!! Form::submit("Update", ["class"=>"btn btn-sm btn-info", "form"=>"editDiscount"]) !!}
@endsection
@section('content')
    <div class="row">
        <div class="col-lg-7">
            <div class="card card-info card-outline">
                <div class="card-body">
                    {!! Form::open(['route'=>["discounts.update", $discount->id], 'method'=>"PUT", "id"=>"editDiscount"]) !!}
                    @include('admin.products.discounts._fields')
                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </div>
@endsection
