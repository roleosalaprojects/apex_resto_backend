@extends('superadmin.layouts.master')

@section('title')
    Edit Color Palette
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="/superadmin">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('superadmin.color-palettes.index') }}">Color Palettes</a></li>
    <li class="breadcrumb-item active">{{ $palette->label }}</li>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h2 style="margin:0;font-size:1.1rem;font-weight:600;">Edit Palette — {{ $palette->label }}</h2>
            @if ($palette->is_default)
                <p style="margin:0.25rem 0 0;color:#0e7490;font-size:0.85rem;">This is the default palette. It cannot be deleted or deactivated.</p>
            @endif
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.color-palettes.update', $palette) }}">
                @csrf
                @method('PUT')
                @include('superadmin.color-palettes._form', ['palette' => $palette])
            </form>
        </div>
    </div>
@endsection
