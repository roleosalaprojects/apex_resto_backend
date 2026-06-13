@extends('layout.app')
@section('header')
    - Edit Reservation
@endsection
@section('title')
    Edit Reservation
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('reservations.index')}}">Reservations</a></li>
    <li class="breadcrumb-item text-muted">Edit</li>
@endsection
@section('actions')
    <button type="submit" class="btn btn-sm btn-success" form="editReservation">Save</button>
@endsection
@section('content')
    <form method="POST" action="{{ route('reservations.update', $reservation->id) }}" id="editReservation">
        @csrf
        @method('PUT')
        @include('admin.restaurant.reservations._fields')
    </form>
@endsection
