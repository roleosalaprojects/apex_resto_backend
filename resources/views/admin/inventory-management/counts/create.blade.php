@extends('layout.app')
@section('header')
    - Create Inventory Count
@endsection
@section('title')
    Create Inventory Count
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item "><a class="" href="{{route('counts.index')}}">Inventory Counts</a></li>
    <li class="breadcrumb-item text-muted">Create Inventory Count</li>
@endsection
@section('actions')
    <button class="btn btn-sm btn-success" form="createCount">Create</button>
@endsection
@section('content')
    {!! Form::open(['route'=>'counts.store', 'id'=>'createCount']) !!}
    @include('admin.inventory-management.counts._fields')
    {!! Form::close() !!}
@endsection
