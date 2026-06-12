<div class="form-group mb-5">
    <label for="name" class="form-label required">Tax Name</label>
    <input type="text" name="name" class="form-control" />
    <span class="text-danger">{{$errors->has('name') ? "Cannot be blank!" : ''}}</span>
</div>

<div class="form-group mb-5">
    <label for="rate" class="form-label required">Tax Rate</label>
    <div class="input-group">
        <input type="number" name="rate" id="rate" class="form-control required" oninput="limitDecimalPlaces(event, 0)" onkeypress="return isNumberKey(event)">
        <div class="input-group-append">
            <span class="input-group-text">%</span>
        </div>
    </div>
    <span class="text-danger">{{$errors->has('rate') ? $errors->first('rate') : ''}}</span>
</div>

@section('script')
    
@endsection
