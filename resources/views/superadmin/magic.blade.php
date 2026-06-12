@extends('superadmin.layouts.master')
@section('title')
    Secret
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
@endsection
@section('content')
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Modifiers</h3>
                </div>
                <div class="card-body">
                    {!! Form::open(["route"=>['hocus.pocus.update', "method"=>"PUT"]]) !!}
                    <div class="form-group">
                        {!! Form::label("hocus-pocus", "Reveal Hocus-Pocus", []) !!}
                        <br>
                        <div class="bootstrap-switch-null bootstrap-switch-undefined bootstrap-switch-undefined bootstrap-switch-undefined bootstrap-switch-undefined bootstrap-switch-undefined bootstrap-switch bootstrap-switch-wrapper bootstrap-switch-focused bootstrap-switch-animate"
                             style="width: 86px;">
                            <div class="bootstrap-switch-container" style="width: 126px; margin-left: 0px;">
                                <input type="checkbox" name="hocuspocus"
                                       {{($receipt->hocus_pocus) ? 'checked=""' : ""}} data-bootstrap-switch=""
                                       data-off-color="danger" data-on-color="success">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        {!! Form::label("blackmagic", "Apply Black Magic", []) !!}
                        <br>
                        <div class="bootstrap-switch-null bootstrap-switch-undefined bootstrap-switch-undefined bootstrap-switch-undefined bootstrap-switch-undefined bootstrap-switch-undefined bootstrap-switch bootstrap-switch-wrapper bootstrap-switch-focused bootstrap-switch-animate"
                             style="width: 86px;">
                            <div class="bootstrap-switch-container" style="width: 126px; margin-left: 0px;">
                                <input type="checkbox" name="blackmagic"
                                       {{($receipt->apply) ? 'checked=""' : ""}} data-bootstrap-switch=""
                                       data-off-color="danger" data-on-color="success">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        {!! Form::label("concoction", "VAT Concoction", []) !!}
                        <div class="input-group">
                            {!! Form::text("concoction", ($receipt->rate) ? $receipt->rate : 0, ["class"=>"form-control", 'required']) !!}
                            <div class="input-group-append">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        {!! Form::label("exempt", "VAT Exempt Concoction", []) !!}
                        <div class="input-group">
                            {!! Form::text("exempt", ($receipt->exempt) ? $receipt->exempt : 0, ["class"=>"form-control", 'required']) !!}
                            <div class="input-group-append">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <div class="form-group">
                            <button class="btn btn-danger" type="submit">Apply</button>
                        </div>
                    </div>
                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
    <script src="{{ asset('plugins/bootstrap-switch/js/bootstrap-switch.min.js') }}"></script>
    <script>
        $(function () {
            $("input[name = 'hocuspocus']").each(function () {
                $(this).bootstrapSwitch('state', $(this).prop('checked'));
            });
            $("input[name = 'blackmagic']").each(function () {
                $(this).bootstrapSwitch('state', $(this).prop('checked'));
            });
        })
    </script>
@endsection
