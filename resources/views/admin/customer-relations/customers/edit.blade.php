@extends('layout.app')
@section('header')
    - Edit Customer
@endsection
@section('title')
    Edit Customer: {{$customer->name}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item "><a class="" href="{{route('customers.index')}}">Customers</a></li>
    <li class="breadcrumb-item text-muted">Edit Customer</li>
@endsection
@section('actions')
    <button type="submit" class="btn btn-info" form="editCustomer">Update</button>
@endsection
@section('content')
    <form action="{{ route('customers.update', $customer->id) }}" method="post" id="editCustomer" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.customer-relations.customers._fields')
    </form>
@endsection
