@extends('layout.app')
@section('header')
    - Create Table
@endsection
@section('title')
    Create Table
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('restaurant-tables.index')}}">Tables</a></li>
    <li class="breadcrumb-item text-muted">Create</li>
@endsection
@section('actions')
    <button type="submit" class="btn btn-sm btn-success" form="createTable">Create</button>
@endsection
@section('content')
    <form method="POST" action="{{ route('restaurant-tables.store') }}" id="createTable">
        @csrf
        @include('admin.restaurant.tables._fields')
    </form>
@endsection
