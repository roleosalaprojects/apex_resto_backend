@extends('admin.layouts.master')
@section('title')

@endsection
@section('breadcrumb-item')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Home</a></li>
@endsection
@section('content')
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>POS Settings</h3>
                </div>
                {!! Form::open(["route"=>"settings.update"]) !!}
                <div class="card-body">
                    <div class="form-group">
                        <div class="icheck-primary d-inline">
                            <input type="checkbox" id="notif" name="notif" {{($settings->notif) ? "checked" : ""}}>
                            <label for="notif">
                                Notify Cashier on low stock.
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="icheck-primary d-inline">
                            <input type="checkbox" id="allow" name="allow" {{($settings->allow) ? "checked" : ""}}>
                            <label for="allow">
                                Allow Cashier to Make a Sale on negative stocks.
                            </label>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-lg btn-success float-right">Save</button>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
@endsection
