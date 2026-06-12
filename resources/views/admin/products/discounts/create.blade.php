@extends('layout.app')
@section('header')
    - Create Discount
@endsection
@section('title')
    New Discount
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{route('discounts.index')}}">Discounts</a></li>
    <li class="breadcrumb-item pe-3 active">Create new Discount</li>
@endsection
@section('actions')
    {!! Form::submit("Create", ["class"=>"btn btn-sm btn-success", "form"=>"createDiscount"]) !!}
@endsection
@section('content')
    <div class="row">
        <div class="col-lg-7">
            <div class="card card-outline">
                <div class="card-body">
                    {!! Form::open(['route'=>"discounts.store", 'id'=>'createDiscount']) !!}
                    @include('admin.products.discounts._fields')
                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </div>
@endsection
