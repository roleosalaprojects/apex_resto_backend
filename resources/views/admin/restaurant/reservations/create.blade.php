@extends('layout.app')
@section('header')
    - Create Reservation
@endsection
@section('title')
    Create Reservation
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('reservations.index')}}">Reservations</a></li>
    <li class="breadcrumb-item text-muted">Create</li>
@endsection
@section('actions')
    <button type="submit" class="btn btn-sm btn-success" form="createReservation">Create</button>
@endsection
@section('content')
    <form method="POST" action="{{ route('reservations.store') }}" id="createReservation">
        @csrf
        @include('admin.restaurant.reservations._fields')
    </form>
@endsection
