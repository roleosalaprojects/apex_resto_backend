@extends('layout.app')
@section('header')
    - New Employee
@endsection
@section('title')
    New Employee
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item "><a class="" href="{{route('employees.index')}}">Employees</a></li>
    <li class="breadcrumb-item  text-muted">New Employee</li>
@endsection
@section('actions')
    <button type="submit" class="btn btn-primary" form="createEmployee">Create</button>
@endsection
@section('content')
    <form action="{{ route('employees.store') }}" method="post" id="createEmployee">
        @csrf
        @include('admin.employees._fields')
    </form>
@endsection
