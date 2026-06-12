@extends('layout.app')
@section('header')
    - Create Shop Announcement
@endsection
@section('title')
    Shop Announcements
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item pe-3"><a class="pe-3" href="{{ route('shop-announcements.index') }}">Shop Announcements</a></li>
    <li class="breadcrumb-item pe-3 text-muted">Create</li>
@endsection
@section('content')
    <form
        action="{{ route('shop-announcements.store') }}"
        method="post"
        id="advertisementForm"
        enctype="multipart/form-data"
    >
        @include('admin.ecommerce.shop-announcements._fields')
    </form>
@endsection
@section('scripts')
    <script src="{{ asset('assets/js/pages/shop-announcements/create-edit.js') }}"></script>
@endsection
