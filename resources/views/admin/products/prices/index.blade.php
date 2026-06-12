@extends('admin.layouts.master')
@section('title')

@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Price Changes</li>
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-8">
                            <h3>Price Changes</h3>
                        </div>
                        <div class="col-4">
                            <a href="{{route('prices.create')}}" class="btn btn-outline-success btn-md float-right">Create</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-hover">
                        <thead>
                        <th style="width: 40%">Created by</th>
                        <th style="width: 20%">Total</th>
                        <th style="width: 20%">Created at</th>
                        <th style="width: 20%">Actions</th>
                        </thead>
                    </table>
                    <tbody>

                    </tbody>
                </div>
            </div>
        </div>
    </div>
@endsection
