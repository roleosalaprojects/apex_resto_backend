@extends('layout.app')
@section('header')
    - Create Advertisements
@endsection
@section('title')
    Advertisements
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('advertisements.index') }}">Advertisements</a></li>
    <li class="breadcrumb-item pe-3 text-muted">Create</li>
@endsection
@section('content')
    <form
            action="{{ route('advertisements.store') }}"
            method="post"
            id="advertisementForm"
            enctype="multipart/form-data"
    >
        @include('admin.settings.advertisements._fields')
    </form>
@endsection
@section('scripts')
    
    <script src="{{ asset('assets/js/pages/advertisements/create-edit.js') }}"></script>
@endsection
