@extends('layout.app')
@section('header')
    - Edit POS
@endsection
@section('title')
    Edit POS: {{ $pos->name }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item "><a class="" href="{{route('pos.index')}}">POS Devices</a></li>
    <li class="breadcrumb-item text-muted">Edit POS Device</li>
@endsection
@section('actions')
    {!! Form::submit("Update", ["class"=>"btn btn-info btn-sm", "form"=>"editPOS"]) !!}
@endsection
@section('content')
    {!! Form::open(['route'=>["pos.update", $pos->id], 'method'=>'PUT', "id"=>"editPOS"]) !!}
    @include('admin.settings.pos._fields')
    {!! Form::close() !!}
@endsection
