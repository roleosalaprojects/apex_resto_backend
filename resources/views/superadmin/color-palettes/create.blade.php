@extends('superadmin.layouts.master')

@section('title')
    New Color Palette
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="/superadmin">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('superadmin.color-palettes.index') }}">Color Palettes</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h2 style="margin:0;font-size:1.1rem;font-weight:600;">New Color Palette</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.color-palettes.store') }}">
                @csrf
                @include('superadmin.color-palettes._form', ['palette' => $palette])
            </form>
        </div>
    </div>
@endsection
