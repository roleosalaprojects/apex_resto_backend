@extends('layout.app')
@section('header')
    - Edit Employee
@endsection
@section('title')
    Edit Employee: {{$user->name}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item "><a class="" href="{{route('employees.index')}}">Employee List</a></li>
    <li class="breadcrumb-item text-muted">Edit Employee</li>
@endsection
@section('actions')
    {!! Form::submit("Create", ["class"=>"btn btn-info btn-sm", "form"=>"editEmployee"]) !!}
@endsection
@section('content')
    {!! Form::open(['route'=>['employees.update', $user->id], 'method'=>'PUT', 'files'=>'true', 'id'=>'editEmployee']) !!}

    @include('admin.employees._fields')

    {!! Form::close() !!}
@endsection
@section('style')
    <link rel="stylesheet" href="{{ asset('plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
@endsection
