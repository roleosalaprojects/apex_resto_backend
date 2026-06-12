@extends('layout.app')
@section('header')
    - Edit Tax
@endsection
@section('title')
    Update Tax : {{$tax->name}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('taxes.index')}}">Taxes</a></li>
    <li class="breadcrumb-item active">Update Tax</li>
@endsection
@section('actions')
    {!! Form::submit("Update", ["class"=>"btn btn-info btn-sm", "form"=>"editTax"]) !!}
@endsection
@section('content')
    <div class="row">
        <div class="co-md-5">
            <div class="card card-info card-outline">
                <div class="card-header">
                    Details
                </div>
                <div class="card-body">
                    {!! Form::open(['route'=>["taxes.update", $tax->id], 'method'=>'PUT', 'id'=>'editTax']) !!}
                    @include('admin.settings.taxes._fields')
                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </div>
@endsection
