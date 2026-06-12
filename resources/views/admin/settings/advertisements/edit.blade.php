@extends('layout.app')
@section('header')
    - Update Advertisements
@endsection
@section('title')
    Update {{ $advertisement->name }} Advertisement
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('advertisements.index') }}">Advertisements</a></li>
    <li class="breadcrumb-item pe-3 text-muted">Update Advertisement</li>
@endsection
@section('content')
    <form
            action="{{ route('advertisements.update', $advertisement->id) }}"
            method="POST"
            id="advertisementForm"
            enctype="multipart/form-data"
    >
        @method('PUT')
        @include('admin.settings.advertisements._fields')
    </form>
@endsection
@section('scripts')
    
    <script src="{{ asset('assets/js/pages/advertisements/create-edit.js') }}"></script>
@endsection
