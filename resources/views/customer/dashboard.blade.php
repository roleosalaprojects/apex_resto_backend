@extends('customer.layouts.app')

@section('content')
<!--begin::Welcome Section-->
<div class="rounded-3 p-8 mb-8" style="background: linear-gradient(135deg, var(--qb-primary), var(--qb-primary-dark));">
    <h1 class="text-white fw-bolder fs-2x mb-1">Welcome, {{ Auth::guard('customer')->user()->name }}!</h1>
    <p class="text-white fs-5 mb-0" style="opacity: 0.9;">Here's an overview of your account.</p>
</div>

<!--begin::Stat Cards-->
<div class="row g-5 mb-8">
    <div class="col-md-6">
        <div class="card qb-card h-100" style="border-left: 4px solid var(--qb-primary) !important;">
            <div class="card-body d-flex align-items-center gap-4">
                <div class="d-flex align-items-center justify-content-center rounded-circle qb-icon-bg" style="width: 56px; height: 56px;">
                    <i class="ki-duotone ki-award fs-2x qb-icon"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                </div>
                <div>
                    <span class="text-gray-500 fw-semibold fs-6">Your Points</span>
                    <br>
                    <span class="fw-bolder fs-2x" style="color: var(--qb-primary-dark);">{{ number_format(Auth::guard('customer')->user()->points ?? 0) }}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card qb-card h-100" style="border-left: 4px solid var(--qb-primary-dark) !important;">
            <div class="card-body d-flex align-items-center gap-4">
                <div class="d-flex align-items-center justify-content-center rounded-circle qb-icon-bg" style="width: 56px; height: 56px;">
                    <i class="ki-duotone ki-barcode fs-2x qb-icon"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span><span class="path6"></span><span class="path7"></span><span class="path8"></span></i>
                </div>
                <div>
                    <span class="text-gray-500 fw-semibold fs-6">Customer Code</span>
                    <br>
                    <span class="fw-bolder fs-2x" style="color: var(--qb-primary-dark);">{{ Auth::guard('customer')->user()->code }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Stat Cards-->

<!--begin::Quick Actions-->
<div class="row g-5 mb-8">
    <div class="col-md-6">
        <a href="{{ route('shops.cart') }}" class="card qb-card h-100 text-decoration-none">
            <div class="card-body d-flex align-items-center gap-4">
                <div class="d-flex align-items-center justify-content-center rounded-circle qb-icon-bg" style="width: 48px; height: 48px;">
                    <i class="ki-duotone ki-handcart fs-2x qb-icon"><span class="path1"></span><span class="path2"></span></i>
                </div>
                <span class="fw-bold fs-4" style="color: #1a1a2e;">My Cart</span>
            </div>
        </a>
    </div>
    <div class="col-md-6">
        <a href="{{ route('customer.orders') }}" class="card qb-card h-100 text-decoration-none">
            <div class="card-body d-flex align-items-center gap-4">
                <div class="d-flex align-items-center justify-content-center rounded-circle qb-icon-bg" style="width: 48px; height: 48px;">
                    <i class="ki-duotone ki-basket fs-2x qb-icon"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                </div>
                <span class="fw-bold fs-4" style="color: #1a1a2e;">My Orders</span>
            </div>
        </a>
    </div>
</div>
<!--end::Quick Actions-->

<!--begin::Account Info-->
<div class="card qb-card">
    <div class="card-header">
        <h3 class="card-title fw-bold" style="color: #1a1a2e;">Account Information</h3>
    </div>
    <div class="card-body pt-0">
        <table class="table table-row-bordered table-row-gray-200 align-middle gy-4">
            <tbody>
                <tr>
                    <td class="fw-semibold text-gray-700 w-200px">Email</td>
                    <td>{{ Auth::guard('customer')->user()->email }}</td>
                </tr>
                <tr>
                    <td class="fw-semibold text-gray-700">Phone</td>
                    <td>{{ Auth::guard('customer')->user()->phone ?? 'Not provided' }}</td>
                </tr>
                <tr>
                    <td class="fw-semibold text-gray-700">Address</td>
                    <td>{{ Auth::guard('customer')->user()->address ?? 'Not provided' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<!--end::Account Info-->
@endsection
