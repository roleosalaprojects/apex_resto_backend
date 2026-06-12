@extends('layout.app')
@section('header')
    - OpenClaw Tokens
@endsection
@section('title')
    OpenClaw Tokens
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted"><span class="">Settings</span></li>
    <li class="breadcrumb-item text-muted">OpenClaw Tokens</li>
@endsection
@section('content')
    @livewire('admin.openclaw-tokens.index')
@endsection
