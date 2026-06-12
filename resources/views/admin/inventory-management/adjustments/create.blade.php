@extends('layout.app')
@section('header')
    - Create Stock Adjustment
@endsection
@section('title')
    Create Stock Adjustment
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('adjustments.index')}}">Stock Adjustments</a></li>
    <li class="breadcrumb-item text-muted">Create new Stock Adjustment</li>
@endsection
@section('actions')
    <button class="btn btn-success btn-sm" form="createAdjustment">Create</button>
@endsection
@section('content')
    {!! Form::open(['route'=>"adjustments.store", 'id'=>'createAdjustment']) !!}
    @include('admin.inventory-management.adjustments._fields')
    {!! Form::close() !!}
@endsection
