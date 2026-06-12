<div class="card">
    <div class="card-body">
        <div class="form-group mb-5">
            <label for="name" class="form-label required">Name</label>
            <input type="text" name="name" id="name" value="{{ $store->name }}" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}">
            <span class="text-danger">{{$errors->has('name') ? "Cannot be blank!" : ''}}</span>
        </div>
        <div class="form-group mb-5">
            <label for="header" class="form-label">Address</label>
            <small class="text-info">(Address will serve as header of the receipt)</small>
            <input type="text" name="header" id="header" value="{{ $store->header }}" class="form-control {{ $errors->has('header') ? 'is-invalid' : '' }}">
            <span class="text-danger">{{$errors->has('header') ? "Cannot be blank!" : ''}}</span>
        </div>
        <div class="form-group mb-5">
            <label for="tin" class="form-label">Tin</label>
            <input type="text" name="tin" id="tin" value="{{ $store->tin }}" class="form-control {{ $errors->has('tin') ? 'is-invalid' : '' }}">
            <span class="text-danger">{{$errors->has('tin') ? "Cannot be blank!" : ''}}</span>
        </div>
        <div class="form-group mb-5">
            <label for="vat_reg" class="form-label">Vat Registered</label>
            <select name="vat_reg" id="vat_reg" class="form-control {{ $errors->has('vat_reg') ? 'is-invalid' : '' }}">
                <option value="1" {{ ($store->vat_reg) ? 'selected' : '' }}>YES</option>
                <option value="0" {{ (!$store->vat_reg) ? 'selected' : '' }}>NO</option>
            </select>
            <span class="text-danger">{{$errors->has('vat_reg') ? "Cannot be blank!" : ''}}</span>
        </div>
        <div class="form-group mb-5">
            <label for="phone" class="form-label">Phone Number</label>
            <input type="text" name="phone" id="phone" value="{{ $store->phone }}" class="form-control {{ $errors->has('phone') ? 'is-invalid' : '' }}">
            <span class="text-danger">{{$errors->has('phone') ? "Cannot be blank!" : ''}}</span>
        </div>
        <div class="form-group mb-5">
            <label for="email" class="form-label">Email</label>
            <input type="text" name="email" id="email" value="{{ $store->email }}" class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}">
            <span class="text-danger">{{$errors->has('email') ? "Cannot be blank!" : ''}}</span>
        </div>
        <div class="row mb-5">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="latitude" class="form-label">Latitude</label>
                    <input type="number" step="0.0000001" name="latitude" id="latitude" value="{{ $store->latitude }}" class="form-control" placeholder="e.g. 14.5995">
                    <small class="text-muted">Store location for weather-based demand forecasting</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="longitude" class="form-label">Longitude</label>
                    <input type="number" step="0.0000001" name="longitude" id="longitude" value="{{ $store->longitude }}" class="form-control" placeholder="e.g. 120.9842">
                    <small class="text-muted">Used with latitude for weather forecasting</small>
                </div>
            </div>
        </div>
        <div class="form-group mb-5">
            <label for="footer" class="form-label">Footer</label>
            <input type="text" name="footer" id="footer" value="{{ $store->footer }}" class="form-control {{ $errors->has('footer') ? 'is-invalid' : '' }}">
            <span class="text-danger">{{$errors->has('footer') ? "Cannot be blank!" : ''}}</span>
        </div>
    </div>
</div>