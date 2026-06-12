<div class="row">
    <div class="col-lg-8">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">Customer Details</div>
            </div>
            <div class="card-body">
                <div class="form-group mb-5">
                    <label for="name" class="form-label required">Name</label>
                    <input type="text" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}" name="name" id="name" value="{{ $customer ? $customer->name : old('name') }}" autocomplete="off">
                    <span class="text-danger">{{$errors->has('name') ? "Name field cannot be empty!" : ''}}</span>
                </div>
                <div class="form-group mb-5">
                    <label for="code" class="form-label required">Customer code</label>
                    <input type="text" class="form-control {{ $errors->has('code') ? 'is-invalid' : '' }}" name="code"
                           id="code" autocomplete="off" value="{{ $customer ? $customer->code : old('code') }}">
                    <span class="text-danger">{{$errors->has('code') ? "Customer code field cannot be empty!" : ''}}</span>
                </div>
                <div class="form-group mb-5">
                    <label for="tin" class="form-label">Tax Identification Number</label>
                    <input type="text" class="form-control" name="tin" id="tin" value="{{ $customer ? $customer->tin : old('tin') }}" autocomplete="off">
                </div>
                <div class="form-group mb-5">
                    <label for="business_type" class="form-label">Business Type:</label>
                    <input type="text" class="form-control" name="business_type" id="business_type" value="{{ $customer ? $customer->business_type : old('business_type') }}" autocomplete="off">
                </div>
                <div class="form-group mb-5">
                    <label for="email" class="form-label required">Email</label>
                    <input type="text" class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}" name="email" id="email" value="{{ $customer ? $customer->email : old('email') }}" autocomplete="off">
                    <span class="text-danger">{{$errors->has('email') ? "Email field cannot be empty!" : ''}}</span>
                </div>
                <div class="form-group mb-5">
                    <label for="phone" class="form-label required">Phone</label>
                    <input type="text" class="form-control {{ $errors->has('phone') ? 'is-invalid' : '' }}" name="phone" id="phone" value="{{ $customer ? $customer->phone : old('name') }}" autocomplete="off">
                    <span class="text-danger">{{$errors->has('phone') ? "Phone field cannot be empty!" : ''}}</span>
                </div>
                <div class="form-group mb-5">
                    <input type="hidden" name="old_image" value="{{$customer->image}}">
                    <label for="exampleInputFile">File input</label>
                    <div class="input-group mb-5">
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="image" name="image">
                            <label class="custom-file-label" for="image">Choose file</label>
                        </div>
                    </div>
                    @error('image')
                    <span class="text-danger">{{$message}}</span>
                    @enderror
                </div>
                <div class="form-group mb-5">
                    <label for="note" class="form-label">Note</label>
                    <input type="text" class="form-control" name="note" id="note">
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-flush mb-5">
            <div class="card-header">
                <div class="card-title">Address</div>
            </div>
            <div class="card-body">
                <div class="form-group mb-5">
                    <label for="address" class="form-label required">Address</label>
                    <input type="text"
                           class="form-control {{ ($errors->has('address') ? 'is-invalid' : '') }}" autocomplete='off' value="{{ $customer ? $customer->address : old('address') }}" name="address" id="address">
                    <span class="text-danger">{{$errors->has('address') ? "Address field cannot be empty!" : ''}}</span>
                </div>
                <div class="row">
                    <div class="col-xs-12 col-sm-12 col-md-12 col-lg-6 col-xl-6">
                        <div class="form-group mb-5">
                            <label for="city" class="form-label required">City</label>
                            <input type="text" class="form-control {{ ($errors->has('city') ? 'is-invalid' : '') }}" value="{{ $customer ? $customer->city : old('city') }}" name="city" id="city">
                            <span class="text-danger">{{$errors->has('city') ? "City field cannot be empty!" : ''}}</span>
                        </div>
                    </div>
                    <div class="col-xs-12 col-sm-12 col-md-12 col-lg-6 col-xl-6">
                        <div class="form-group mb-5">
                            <label for="zip" class="form-label required">Zip Code</label>
                            <input type="text" class="form-control {{ ($errors->has('zip') ? 'is-invalid' : '') }}" value="{{ $customer ? $customer->zip : old('zip') }}" name="zip" id="zip">
                            <span class="text-danger">{{$errors->has('zip') ? "Zip field cannot be empty!" : ''}}</span>
                        </div>
                    </div>
                    <div class="col-xs-12 col-sm-12 col-md-12 col-lg-6 col-xl-6">
                        <div class="form-group mb-5">
                            <label for="province" class="form-label required">Province</label>
                            <input type="text" class="form-control {{ ($errors->has('province') ? 'is-invalid' : '') }}" value="{{ $customer ? $customer->province : old('province') }}" name="province" id="province">
                            <span class="text-danger">{{$errors->has('province') ? "Province field cannot be empty!" : ''}}</span>
                        </div>
                    </div>
                    <div class="col-xs-12 col-sm-12 col-md-12 col-lg-6 col-xl-6">
                        <div class="form-group mb-5">
                            <label for="country" class="form-label required">Country</label>
                            {{-- {!! Form::text("country", $customer->country, ["class"=>'form-control']) !!} --}}
                            <input list="country" value="{{ $customer ? $customer->country : old('country') }}" name="country" class="form-control {{($errors->has('country') ? 'is-invalid' : '')}}" />
                            <span class="text-danger">{{$errors->has('country') ? "Country field cannot be empty!" : ''}}</span>
                            @include('admin.partials.countries')
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{--        @if(\Route::current()->getName() == 'customers.create')--}}
        {{--            <div class="card card-flush mb-5">--}}
        {{--                <div class="card-header">--}}
        {{--                    <div class="card-title">--}}
        {{--                        Credentials--}}
        {{--                    </div>--}}
        {{--                </div>--}}
        {{--                <div class="card-body">--}}
        {{--                    <div class="form-group mb-5">--}}
        {{--                        {!! Form::label("email", "Email", ["class"=>"form-label required"]) !!}--}}
        {{--                        {!! Form::text("email", $customer->email, ["class"=>"form-control  ".($errors->has('email') ? 'is-invalid' : ''), 'autocomplete'=>'nope']) !!}--}}
        {{--                        <span class="text-danger">{{$errors->has('email') ? "Email field cannot be empty!" : ''}}</span>--}}
        {{--                    </div>--}}
        {{--                    <div class="form-group mb-5">--}}
        {{--                        {!! Form::label("password", "Password", ["class"=>"form-label required"]) !!}--}}
        {{--                        {!! Form::password("password", ["class"=>"form-control  ".($errors->has('password') ? 'is-invalid' : ''), 'autocomplete'=>'nope']) !!}--}}
        {{--                        <span class="text-danger">{{$errors->has('password') ? "Passwords Error!" : ''}}</span>--}}
        {{--                    </div>--}}
        {{--                    <div class="form-group mb-5">--}}
        {{--                        {!! Form::label("password_confirmation", "Confirm Password", ["class"=>"form-label required"]) !!}--}}
        {{--                        {!! Form::password("password_confirmation", ["class"=>"form-control  ".($errors->has('password') ? 'is-invalid' : ''), 'autocomplete'=>'nope']) !!}--}}
        {{--                        <span class="text-danger">{{$errors->has('password_confirmation') ? "Passwords Error!" : ''}}</span>--}}
        {{--                    </div>--}}
        {{--                </div>--}}
        {{--            </div>--}}
        {{--        @endif--}}

        <div class="card card-flush mb-5">
            <div class="card-header">
                <div class="card-title">
                    Emergency Contact
                </div>
            </div>
            <div class="card-body">
                <div class="form-group mb-5">
                    <label for="e_name" class="form-label">Name</label>
                    <input type="text" class="form-control" name="e_name" id="e_name" value="{{ $customer ? $customer->e_name : old('e_name') }}">
                </div>
                <div class="form-group mb-5">
                    <label for="e_phone" class="form-label">Phone</label>
                    <input type="text" class="form-control" name="e_phone" id="e_phone" value="{{ $customer ? $customer->e_phone : old('e_phone') }}">
                </div>
                <div class="form-group mb-5">
                    <label for="e_address" class="form-label">Address</label>
                    <input type="text" class="form-control" name="e_address" id="e_address" value="{{ $customer ? $customer->e_address : old('e_address') }}">
                </div>
            </div>
        </div>

        @if (\Route::current()->getName() == 'customers.edit')
            <div class="card card-flush mb-5">
                <div class="card-header">
                    <div class="card-title">Credit Settings</div>
                </div>
                <div class="card-body">
                    <div class="form-group mb-5">
                        <label for="credit_limit" class="form-label">Credit Limit</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="credit_limit" id="credit_limit"
                               value="{{ $customer->credit_limit ?? 0 }}">
                        <small class="text-muted">Set to 0 to disable credit for this customer.</small>
                    </div>
                    <div class="form-group mb-5">
                        <label for="credit_term_days" class="form-label">Credit Term (days)</label>
                        <input type="number" min="1" class="form-control" name="credit_term_days" id="credit_term_days"
                               value="{{ $customer->credit_term_days ?? 30 }}">
                        <small class="text-muted">Number of days before credit payment is due.</small>
                    </div>
                    <div class="form-group">
                        <label for="credit_balance" class="form-label">Outstanding Balance</label>
                        <input type="number" class="form-control" name="credit_balance" id="credit_balance"
                               value="{{ $customer->credit_balance ?? 0 }}" readonly>
                        <small class="text-muted">Current unpaid credit balance (read-only).</small>
                    </div>
                </div>
            </div>

            <div class="card card-flush">
                <div class="card-header">
                    <div class="card-title">Points Modifier</div>
                </div>
                <div class="card-body">
                    <div class="form-group mb-5">
                        <label for="points" class="form-label required">Points Multiplier</label>
                        <input type="number" class="form-control " name="points" id="points"
                               value="{{ ($customer->points) ? number_format($customer->points, 5) : 0.00 }}">
                    </div>
                    <div class="form-group">
                        <label for="accumulated_points" class="form-label">Accumulated Points</label>
                        <input type="number" class="form-control required" name="accumulated_points"
                               id="accumulated_points" value="{{ ($customer->accumulated_points) ?? 0 }}">
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
