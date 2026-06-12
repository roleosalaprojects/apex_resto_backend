@if (session()->has('msg'))
    <div class="alert alert-success alert-dismissible">
        {{session()->get('msg')}}
    </div>
@endif
@if (session()->has('danger'))
    <div class="alert alert-danger alert-dismissible">
        {{session()->get('danger')}}
    </div>
@endif
@if ($errors->has('error-msg'))
    <div class="alert alert-danger alert-dismissible">
        {{$errors->first('error-msg')}}
    </div>
@endif
@if (session('error'))
<div class="alert alert-danger alert-dismissible">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
    <h5><i class="icon fas fa-ban"></i> Error!</h5>
    {{session('error')}}
</div>
@endif
