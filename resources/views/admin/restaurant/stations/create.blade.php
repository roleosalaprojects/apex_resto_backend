@extends('layout.app')
@section('header')
    - Create Kitchen Station
@endsection
@section('title')
    Create Kitchen Station
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('kitchen-stations.index')}}">Kitchen Stations</a></li>
    <li class="breadcrumb-item text-muted">Create</li>
@endsection
@section('actions')
    <button type="submit" class="btn btn-sm btn-success" form="createStation">Create</button>
@endsection
@section('content')
    <form method="POST" action="{{ route('kitchen-stations.store') }}" id="createStation">
        @csrf
        @include('admin.restaurant.stations._fields')
    </form>
@endsection
