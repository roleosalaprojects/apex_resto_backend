@extends('layout.app')
@section('header')
    - Customer
@endsection
@section('title')
    New Customer
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item "><a class="" href="{{route('customers.index')}}">Customers</a></li>
    <li class="breadcrumb-item text-muted">New Customer</li>
@endsection
@section('actions')
    <button type="submit" class="btn btn-primary float-right" form="createCustomer">Save</button>
@endsection
@section('content')
    <form action="{{ route('customers.store') }}" id="createCustomer" method="POST" enctype="multipart/form-data">
        @csrf
        @include('admin.customer-relations.customers._fields')
    </form>
@endsection
