@extends('layout.app')
@section('header')
    - Edit Store
@endsection
@section('title')
    Update Store : {{$store->name}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('stores.index')}}">Stores</a></li>
    <li class="breadcrumb-item text-muted">Update Store</li>
@endsection
@section('actions')
    <button type="submit" class="btn btn-sm btn-info" form="editStore">Update</button>
@endsection
@section('content')
    <form method="POST" action="{{ route('stores.update', $store->id) }}" id="editStore">
    @csrf
    @method('PUT')
    @include('admin.settings.stores._fields')
    </form>
@endsection
