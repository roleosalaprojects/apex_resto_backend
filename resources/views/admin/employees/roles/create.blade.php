@extends('layout.app')
@section('header')
    - New Role
@endsection
@section('title')
    New Role / Position
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('roles.index')}}">Roles</a></li>
    <li class="breadcrumb-item text-muted">Create new Role</li>
@endsection
@section('actions')
    <button type="submit" class="btn btn-sm btn-success" form="createRole">Create</button>
@endsection
@section('content')
    <form method="POST" action="{{ route('roles.store') }}" id="createRole">
    @csrf
    @include('admin.employees.roles._fields')
    </form>
@endsection
