@extends('customer.layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-6">
        <h1 class="fw-bolder fs-2x mb-0" style="color: #1a1a2e;">Change Password</h1>
        <a href="{{ route('customer.profile.edit') }}" class="btn fw-semibold btn-light" style="border-radius: 8px;">
            <i class="ki-duotone ki-arrow-left fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
            Back to Profile
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger d-flex align-items-start mb-5" style="border-radius: 8px;">
            <i class="ki-duotone ki-shield-cross fs-2x me-3 text-danger"><span class="path1"></span><span class="path2"></span></i>
            <div>
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card qb-card">
        <div class="card-body p-8">
            <form action="{{ route('customer.password.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-5">
                    <label class="form-label fw-semibold required">Current Password</label>
                    <input type="password" name="current_password" autocomplete="current-password"
                           class="form-control @error('current_password') is-invalid @enderror" style="border-radius: 8px;" required>
                    @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-5">
                    <label class="form-label fw-semibold required">New Password</label>
                    <input type="password" name="password" autocomplete="new-password"
                           class="form-control @error('password') is-invalid @enderror" style="border-radius: 8px;" required>
                    <small class="text-gray-500 d-block mt-1">Must be at least 8 characters and different from your current password.</small>
                    @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="mb-8">
                    <label class="form-label fw-semibold required">Confirm New Password</label>
                    <input type="password" name="password_confirmation" autocomplete="new-password"
                           class="form-control" style="border-radius: 8px;" required>
                </div>

                <div class="d-flex justify-content-end gap-3">
                    <a href="{{ route('customer.profile.edit') }}" class="btn btn-light fw-semibold" style="border-radius: 8px;">Cancel</a>
                    <button type="submit" class="btn fw-bold qb-btn-primary" style="border-radius: 8px;">Update Password</button>
                </div>
            </form>
        </div>
    </div>
@endsection
