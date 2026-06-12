<div class="form-group mb-5">
    {!! Form::label("name", "Name", ["class"=>"form-label"]) !!}
    {!! Form::text("name", $discount->name, ["class"=>'form-control ' . ($errors->has('name') ? 'is-invalid' : '')]) !!}
    <span class="text-danger">{{$errors->has('name') ? "Cannot be blank!" : ''}}</span>
</div>
<div class="form-group">
    {!! Form::label("rate", "Rate", ["class"=>"form-label"]) !!}
    <div class="input-group mb-3">
        {!! Form::text("rate", $discount->rate, ["class"=>'form-control ' . ($errors->has('rate')? 'is-invalid' : ''), 'onkeypress'=>'return isNumberKey(event)', 'oninput'=>'limitDecimalPlaces(event, 0)']) !!}
    <div class="input-group-append">
            <span class="input-group-text">%</span>
        </div>
    </div>
    <span class="text-danger">{{$errors->has('rate') ? $errors->first('rate') : ''}}</span>
</div>

@section('style')
    
@endsection