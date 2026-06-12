@extends('layout.app')
@section('header')
    - Create Store
@endsection
@section('title')
    Create Store
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('stores.index')}}">Stores</a></li>
    <li class="breadcrumb-item text-muted">Create new Store</li>
@endsection
@section('actions')
    <button type="submit" class="btn btn-sm btn-success" form="createStore">Create</button>
@endsection
@section('content')
    <form method="POST" action="{{ route('stores.store') }}" id="createStore">
    @csrf
    @include('admin.settings.stores._fields')
    </form>
@endsection
