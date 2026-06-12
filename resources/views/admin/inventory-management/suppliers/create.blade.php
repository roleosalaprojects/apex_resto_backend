@extends('layout.app')
@section('header')
    - Create Supplier
    @endsection- Create Supplier
    @section('title')
        Create Supplier
    @endsection
    @section('breadcrumb')
        <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
        <li class="breadcrumb-item"><a class="" href="{{route('suppliers.index')}}">Suppliers</a></li>
        <li class="breadcrumb-item text-muted">New Supplier</li>
    @endsection
    @section('actions')
        <button class="btn btn-sm btn-success" form="createSupplier">Create</button>
    @endsection
    @section('content')
        {!! Form::open(['route'=>['suppliers.store'], 'id'=>'createSupplier']) !!}
        @include('admin.inventory-management.suppliers._fields')
        {!! Form::close() !!}
    @endsection
