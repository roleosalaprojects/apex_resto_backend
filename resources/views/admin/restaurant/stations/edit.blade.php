@extends('layout.app')
@section('header')
    - Edit Kitchen Station
@endsection
@section('title')
    Edit Kitchen Station
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('kitchen-stations.index')}}">Kitchen Stations</a></li>
    <li class="breadcrumb-item text-muted">Edit</li>
@endsection
@section('actions')
    <button type="submit" class="btn btn-sm btn-success" form="editStation">Save</button>
@endsection
@section('content')
    <form method="POST" action="{{ route('kitchen-stations.update', $station->id) }}" id="editStation">
        @csrf
        @method('PUT')
        @include('admin.restaurant.stations._fields')
    </form>
@endsection
