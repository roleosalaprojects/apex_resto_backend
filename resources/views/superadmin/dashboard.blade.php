@extends('superadmin.layouts.master')

@section('title')
    Dashboard
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="/superadmin">Home</a></li>
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="page-title">Welcome back!</h1>
        <p class="page-subtitle">Here's what's happening with your system today.</p>
    </div>

    <div class="row">
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">{{ \App\Models\User::where('status', true)->count() }}</div>
                    <div class="stat-label">Active Users</div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-store"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">{{ \App\Models\Settings\Store::where('status', true)->count() }}</div>
                    <div class="stat-label">Active Stores</div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-cash-register"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">{{ \App\Models\Settings\Pos::where('status', true)->count() }}</div>
                    <div class="stat-label">Active Terminals</div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">{{ \App\Models\Products\Item::where('status', true)->count() }}</div>
                    <div class="stat-label">Active Products</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Users</h3>
                    <a href="{{ route('admin.index') }}" class="btn btn-sm btn-outline">View All</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(\App\Models\User::latest()->take(5)->get() as $user)
                            <tr>
                                <td><strong>{{ $user->name }}</strong></td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @if($user->status)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-danger">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $user->created_at->format('M d, Y') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <a href="{{ route('admin.create') }}" class="btn btn-primary" style="justify-content: flex-start;">
                            <i class="fas fa-user-plus"></i>
                            Add New User
                        </a>
                        <a href="{{ route('receipt.index') }}" class="btn btn-outline" style="justify-content: flex-start;">
                            <i class="fas fa-receipt"></i>
                            Receipt Settings
                        </a>
                        <a href="{{ route('admin.index') }}" class="btn btn-outline" style="justify-content: flex-start;">
                            <i class="fas fa-users-cog"></i>
                            Manage Users
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
