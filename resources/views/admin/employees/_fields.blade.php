<div class="row">
  <div class="col-lg-8">
    <div class="card card-flush mb-5">
      <div class="card-header">
        <div class="card-title">
          Employee Details
        </div>
      </div>
      <div class="card-body">
        <div class="form-group mb-5">
            <label for="name" class="form-label required">Name</label>
            <input type="text" name="name" id="name" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}" value="{{ old('name', $user->name ?? '') }}" autocomplete="off">
          <span class="text-danger">{{$errors->has('name') ? "Name field cannot be empty!" : ''}}</span>
        </div>
        <div class="form-group mb-5">
            <label for="role" class="form-label required">Role</label>
            &nbsp
            @if (count($roles) == 0)
                <span class="text-danger">Please add another role before proceeding</span>
            @endif
            <select class="form-select {{ $errors->has('role') ? 'is-invalid' : '' }}" name="role" id="role" data-value="{{ $selected_role }}">
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" {{ $selected_role == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                @endforeach
            </select>
            <span class="text-danger">{{$errors->has('role') ? "Role field cannot be empty!" : ''}}</span>
        </div>
        <div class="form-group mb-5">
            <label for="code" class="form-label">Barcode</label>
            <input type="text" name="code" id="code" class="form-control {{ $errors->has('code') ? 'is-invalid' : '' }}" value="{{ old('code', $user->code ?? '') }}" autocomplete="off" placeholder="Employee barcode/ID code">
            <span class="text-danger">{{$errors->has('code') ? $errors->first('code') : ''}}</span>
        </div>
        <div class="form-group mb-5">
            <label for="phone" class="form-label required">Phone</label>
            <input type="text" name="phone" id="phone" class="form-control {{ $errors->has('phone') ? 'is-invalid' : '' }}" value="{{ old('phone', $user->phone ?? '') }}" autocomplete="off">
            <span class="text-danger">{{$errors->has('phone') ? "Phone field cannot be empty!" : ''}}</span>
        </div>
        <div class="form-group mb-5">
            <label for="address" class="form-label required">Address</label>
            <input type="text" name="address" id="address" class="form-control {{ $errors->has('address') ? 'is-invalid' : '' }}" value="{{ old('address', $user->address ?? '') }}" autocomplete="off">
            <span class="text-danger">{{$errors->has('address') ? "Address field cannot be empty!" : ''}}</span>
        </div>
        <div class="form-group mb-5">
            <label for="email" class="form-label required">Email</label>
            <input type="text" name="email" id="email" class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}" value="{{ old('email', $user->email ?? '') }}" autocomplete="off">
            <span class="text-danger">{{$errors->has('email') ? "Email field cannot be empty!" : ''}}</span>
        </div>
        <div class="form-group mb-10">
            <label for="password" class="form-label required">Password</label>
            <input type="password" name="password" id="password" class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}" autocomplete="off">
          <span class="text-danger">{{$errors->has('password') ? "Passwords do not match!" : ''}}</span>
        </div>
        <div class="form-group mb-10">
            <label for="password_confirmation" class="form-label required">Confirm Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control {{ $errors->has('password_confirmation') ? 'is-invalid' : '' }}" autocomplete="off">
            <span class="text-danger">{{$errors->has('password') ? "Passwords do not match!" : ''}}</span>
        </div>
        <div class="form-group mb-5">
            <input type="hidden" name="old_image" value="{{$user->image}}">
            <label for="exampleInputFile" class="form-label">Choose Profile Picture</label>
            <div class="input-group">
              <div class="custom-file">
                <input type="file" class="custom-file-input form-control" id="image" name="image">
              </div>
            </div>
            @error('image')
                <span class="text-danger">{{$message}}</span>
            @enderror
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card card-flush">
      <div class="card-header">
          <div class="card-title required">Store Availability</div>
      </div>
      <div class="card-body">
          @foreach ($stores as $store)
              <div class="form-group">
                  <div class="icheck-primary d-inline">
                      <input type="checkbox" id="{{$store->id}}" name="stores[]" value="{{$store->id}}"
                      @if (Route::current()->getName() == "employees.edit")
                        @foreach ($employeeStores as $es)
                          {{($es->store_id == $store->id && $es->status == true) ? 'checked' : ''}}
                        @endforeach
                      @endif
                      >
                      <label for="{{$store->id}}" class="form-label">
                          {{$store->name}}
                      </label>
                  </div>
              </div>
          @endforeach
      </div>
    </div>
  </div>
</div>
