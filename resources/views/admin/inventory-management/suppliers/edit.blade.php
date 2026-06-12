@extends('layout.app')
@section('header')
    - Update Supplier
@endsection
@section('title')
    Update Supplier
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('suppliers.index')}}">Suppliers</a></li>
    <li class="breadcrumb-item text-muted">Edit Supplier : {{$supplier->name}}</li>
@endsection
@section('actions')
    <button type="submit" class="btn btn-info btn-sm" form="editSupplier">Update</button>
@endsection
@section('content')
    {!! Form::open([
        'route'=>['suppliers.update', $supplier->id],
        "method"=>"PUT",
        'id' => 'editSupplier']) !!}
    @include('admin.inventory-management.suppliers._fields')
    {!! Form::close() !!}
@endsection
