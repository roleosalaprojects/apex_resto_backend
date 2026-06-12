<div class="form-group">
    <label for="name" class="form-label required">Full Name</label>
    <input type="text" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}" name="name" id="name" value="{{ old('name') }}" placeholder="Enter full name">
    @if($errors->has('name'))
        <span class="text-danger" style="font-size: 12px; margin-top: 0.25rem; display: block;">{{ $errors->first('name') }}</span>
    @endif
</div>

<div class="form-group">
    <label for="phone" class="form-label required">Phone Number</label>
    <input type="text" class="form-control {{ $errors->has('phone') ? 'is-invalid' : '' }}" name="phone" id="phone" value="{{ old('phone') }}" placeholder="Enter phone number">
    @if($errors->has('phone'))
        <span class="text-danger" style="font-size: 12px; margin-top: 0.25rem; display: block;">{{ $errors->first('phone') }}</span>
    @endif
</div>

<div class="form-group">
    <label for="address" class="form-label required">Address</label>
    <input type="text" class="form-control {{ $errors->has('address') ? 'is-invalid' : '' }}" name="address" id="address" value="{{ old('address') }}" placeholder="Enter address">
    @if($errors->has('address'))
        <span class="text-danger" style="font-size: 12px; margin-top: 0.25rem; display: block;">{{ $errors->first('address') }}</span>
    @endif
</div>

<div class="form-group">
    <label for="email" class="form-label required">Email Address</label>
    <input type="email" class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}" name="email" id="email" value="{{ old('email') }}" placeholder="Enter email address">
    @if($errors->has('email'))
        <span class="text-danger" style="font-size: 12px; margin-top: 0.25rem; display: block;">{{ $errors->first('email') }}</span>
    @endif
</div>

<div class="form-group">
    <label for="password" class="form-label required">Password</label>
    <input type="password" class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}" name="password" id="password" placeholder="Enter password">
    @if($errors->has('password'))
        <span class="text-danger" style="font-size: 12px; margin-top: 0.25rem; display: block;">{{ $errors->first('password') }}</span>
    @endif
</div>

<div class="form-group">
    <label for="password_confirmation" class="form-label required">Confirm Password</label>
    <input type="password" class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}" name="password_confirmation" id="password_confirmation" placeholder="Confirm password">
</div>
