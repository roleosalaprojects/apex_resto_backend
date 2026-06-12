@extends('layout.app')
@section('header')
    - Create POS
@endsection
@section('title')
    New POS Device
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item "><a class="" href="{{route('pos.index')}}">POS Devices</a></li>
    <li class="breadcrumb-item text-muted">Create POS Device</li>
@endsection
@section('actions')
    <button type="submit" class="btn btn-sm btn-primary" form="createPOS">Create</button>
    {{--    {!! Form::submit("Create", ["class"=>"btn btn-sm btn-success", "form"=>"createPOS"]) !!}--}}
@endsection
@section('content')
    <form method="POST" action="{{route('pos.store')}}" id="createPOS">
        @csrf
        @method('POST')
        @include('admin.settings.pos._fields')
    </form>
    {{--    {!! Form::open(['route'=>"pos.store", "id"=>"createPOS"]) !!}--}}
    {{--    {!! Form::close() !!}--}}
@endsection
