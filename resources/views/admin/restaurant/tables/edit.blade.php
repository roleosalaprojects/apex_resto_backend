@extends('layout.app')
@section('header')
    - Edit Table
@endsection
@section('title')
    Edit Table
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('restaurant-tables.index')}}">Tables</a></li>
    <li class="breadcrumb-item text-muted">Edit</li>
@endsection
@section('actions')
    <button type="submit" class="btn btn-sm btn-success" form="editTable">Save</button>
@endsection
@section('content')
    <form method="POST" action="{{ route('restaurant-tables.update', $table->id) }}" id="editTable">
        @csrf
        @method('PUT')
        @include('admin.restaurant.tables._fields')
    </form>
@endsection
