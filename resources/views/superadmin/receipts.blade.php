@extends('superadmin.layouts.master')

@section('title')
    Receipt Settings
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Receipt Settings</li>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="page-title">Receipt Settings</h1>
        <p class="page-subtitle">Configure receipt information and defaults</p>
    </div>

    <div class="row">
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Receipt Details</h3>
                </div>
                <div class="card-body">
                    {!! Form::open(['route'=>['receipt.update', $receipt->id], 'method'=>'PUT']) !!}

                    <div class="form-group">
                        {!! Form::label("name", "Business Name", ['class' => 'form-label required']) !!}
                        {!! Form::text("name", $receipt->name, ["class"=>'form-control ' . ($errors->has('name') ? 'is-invalid' : ''), 'placeholder' => 'Enter business name']) !!}
                        @if($errors->has('name'))
                            <span class="text-danger" style="font-size: 12px;">{{ $errors->first('name') }}</span>
                        @endif
                    </div>

                    <div class="form-group">
                        {!! Form::label("header", "Receipt Header", ['class' => 'form-label required']) !!}
                        {!! Form::text("header", $receipt->header, ["class"=>'form-control ' . ($errors->has('header') ? 'is-invalid' : ''), 'placeholder' => 'Enter receipt header']) !!}
                        @if($errors->has('header'))
                            <span class="text-danger" style="font-size: 12px;">{{ $errors->first('header') }}</span>
                        @endif
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                {!! Form::label("tin", "TIN Number", ['class' => 'form-label required']) !!}
                                {!! Form::text("tin", $receipt->tin, ["class"=>'form-control ' . ($errors->has('tin') ? 'is-invalid' : ''), 'placeholder' => 'Enter TIN']) !!}
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                {!! Form::label("vat_reg", "VAT Registered", ['class' => 'form-label']) !!}
                                {!! Form::select("vat_reg", [false=>'No', true=>"Yes"], $receipt->vat_reg, ["class"=>'form-control']) !!}
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                {!! Form::label("email", "Email Address", ['class' => 'form-label']) !!}
                                {!! Form::text("email", $receipt->email, ["class"=>'form-control', 'placeholder' => 'Enter email']) !!}
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                {!! Form::label("phone", "Phone Number", ['class' => 'form-label']) !!}
                                {!! Form::text("phone", $receipt->phone, ["class"=>'form-control', 'placeholder' => 'Enter phone']) !!}
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                {!! Form::label("ptu", "Permit To Use #", ['class' => 'form-label']) !!}
                                {!! Form::text("ptu", $receipt->ptu, ["class"=>'form-control', 'placeholder' => 'Enter PTU number']) !!}
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                {!! Form::label("accredition", "Accreditation #", ['class' => 'form-label']) !!}
                                {!! Form::text("accredition", $receipt->accredition, ["class"=>'form-control', 'placeholder' => 'Enter accreditation number']) !!}
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        {!! Form::label("footer", "Receipt Footer", ['class' => 'form-label']) !!}
                        {!! Form::text("footer", $receipt->footer, ["class"=>'form-control', 'placeholder' => 'Enter receipt footer']) !!}
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                {!! Form::label("points", "Default Points Rate", ['class' => 'form-label']) !!}
                                {!! Form::text("points", ($receipt->points) ? number_format($receipt->points, 5) : '0.00001', ["class"=>'form-control', 'onkeypress'=>'return isNumberKey(event)', 'oninput'=>'limitDecimalPlaces(event, 5)']) !!}
                                <small class="text-muted" style="font-size: 11px;">Points earned per peso spent</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                {!! Form::label("display", "Display on Receipt", ['class' => 'form-label']) !!}
                                {!! Form::select("display", [false=>'No', true=>"Yes"], $receipt->display, ["class"=>'form-control']) !!}
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Update Settings
                        </button>
                    </div>

                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
function isNumberKey(evt) {
    var charCode = (evt.which) ? evt.which : evt.keyCode;
    if (charCode != 46 && charCode > 31 && (charCode < 48 || charCode > 57))
        return false;
    return true;
}

function limitDecimalPlaces(e, count) {
    if (e.target.value.indexOf('.') !== -1) {
        if (e.target.value.split(".")[1].length > count) {
            e.target.value = parseFloat(e.target.value).toFixed(count);
        }
    }
}
</script>
@endsection
