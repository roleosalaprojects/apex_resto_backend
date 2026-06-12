@extends('layout.app')
@section('header')
    - Create Tax
@endsection
@section('title')
    Create Tax
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a class="" href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a class="" href="{{route('taxes.index')}}">Taxes</a></li>
    <li class="breadcrumb-item text-muted">Create new Tax</li>
@endsection
@section('actions')
    <button type="submit" form="createTax" class="btn btn-primary">Create</button>
@endsection
@section('content')
    <div class="row">
        <div class="col-md-5">
            <div class="card card-success card-outline">
                <div class="card-body">
                    <form action="{{route('taxes.store')}}" method="post">
                        @csrf
                        @include('admin.settings.taxes._fields')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
