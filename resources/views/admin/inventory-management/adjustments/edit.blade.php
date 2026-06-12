@extends('layout.app')
@section('header')
    - Edit Stock Adjustment
@endsection
@section('title')
    Edit Stock Adjustment #: {{ $adjustment->so }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{route('adjustments.index')}}">Stock Adjustments</a></li>
    <li class="breadcrumb-item"><a href="{{route('adjustments.show', $adjustment->id)}}">SA #:{{$adjustment->so}}</a>
    </li>
    <li class="breadcrumb-item text-muted">Edit SO :{{$adjustment->so}}</li>
@endsection
@section('actions')
    <button class="btn btn-sm btn-info" form="editAdjustment">Update</button>
@endsection
@section('content')
    {!! Form::open(['route'=>["adjustments.update", $adjustment->id], 'method'=>'PUT', 'id'=>'editAdjustment']) !!}
    @include('admin.inventory-management.adjustments._fields')
    {!! Form::close() !!}
@endsection
