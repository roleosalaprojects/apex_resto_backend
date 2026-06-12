@extends('layout.app')
@section('header')
    - Profile
@endsection
@section('title')
    {{auth()->user()->name}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">Profile</li>
@endsection
@section('content')
    <div class="row">
        <div class="col-lg-6 col-xl-6">
            <div class="card card-flush">
                <div class="card-header">
                    <div class="card-title">User Details</div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                           <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group mb-5">
                                <label for="" class="form-label">Choose Profile Picture</label>
                                <br>
                                <!--begin::Image input-->
                                <div class="image-input image-input-empty" data-kt-image-input="true" style="background-image: url({{ (auth()->user()->details->image) ? asset(auth()->user()->details->image) : asset('/assets/media/svg/avatars/blank.svg') }})">
                                    <!--begin::Image preview wrapper-->
                                    <div class="image-input-wrapper w-125px h-125px"></div>
                                    <!--end::Image preview wrapper-->

                                    <!--begin::Edit button-->
                                    <label class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="change"
                                    data-bs-toggle="tooltip"
                                    data-bs-dismiss="click"
                                    title="Change avatar">
                                        <i class="bi bi-pencil-fill fs-7"></i>

                                        <!--begin::Inputs-->
                                        <input type="file" name="image" accept=".png, .jpg, .jpeg" />
                                        <input type="hidden" name="old_image" value="{{auth()->user()->details->image}}">
                                        <!--end::Inputs-->
                                    </label>
                                    <!--end::Edit button-->

                                    <!--begin::Cancel button-->
                                    <span class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="cancel"
                                    data-bs-toggle="tooltip"
                                    data-bs-dismiss="click"
                                    title="Cancel avatar">
                                        <i class="bi bi-x fs-2"></i>
                                    </span>
                                    <!--end::Cancel button-->

                                    <!--begin::Remove button-->
                                    <span class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="remove"
                                    data-bs-toggle="tooltip"
                                    data-bs-dismiss="click"
                                    title="Remove avatar">
                                        <i class="bi bi-x fs-2"></i>
                                    </span>
                                    <!--end::Remove button-->
                                </div>
                                <!--end::Image input-->
                                @error('image')
                                    <span class="text-danger">{{$message}}</span>
                                @enderror
                            </div>
                            <div class="form-group mb-5">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" name="name" id="name" value="{{ $profile->name }}" class="form-control" disabled>
                            </div>
                            <div class="form-group mb-5">
                                <label for="email" class="form-label">Email</label>
                                <input type="text" name="email" id="email" value="{{ $profile->email }}" class="form-control" disabled>
                            </div>
                            <div class="form-group mb-5">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" name="phone" id="phone" value="{{ $profile->phone }}" class="form-control">
                            </div>
                            <div class="form-group mb-5">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" name="address" id="address" value="{{ $profile->address }}" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-end">
                        <div class="form-group">
                            <button type="submit" class="btn btn-active-color-info btn-bg-light">Update</button>
                        </div>
                    </div>
                </div>
                </form>
            </div>
        </div>
        <div class="col-lg-6 col-xl-6">
            <div class="card card-flush">
                <div class="card-header">
                    <div class="card-title">Update Password</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.update.password') }}">
                    @csrf
                    <div class="form-group mb-5">
                        <label for="password" class="form-label required">New Password</label>
                        <input type="password" name="password" id="password" class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}" autocomplete="off" minlength="6">
                        <span class="text-danger">{{$errors->has('password') ? "Passwords do not match!" : ''}}</span>
                    </div>
                    <div class="form-group mb-5">
                        <label for="password_confirmation" class="form-label required">Confirm New Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control {{ $errors->has('password_confirmation') ? 'is-invalid' : '' }}" minlength="6">
                        <span class="text-danger">{{$errors->has('password_confirmation') ? $errors->first("password_confirmation") : ''}}</span>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-active-color-info btn-bg-light">Update</button>
                    </div>
                </div>
                </form>
            </div>
        </div>
    </div>
@endsection
@section('script')
    <script src="{{ asset('plugins/bs-custom-file-input/bs-custom-file-input.min.js') }}"></script>
    <script>
    
        $(function () {
        bsCustomFileInput.init();
        });
    </script>
@endsection