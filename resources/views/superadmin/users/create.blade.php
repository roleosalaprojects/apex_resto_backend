@extends('superadmin.layouts.master')

@section('title')
    Create User
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="/superadmin">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Users</a></li>
    <li class="breadcrumb-item active">New User</li>
@endsection

@section('content')
    <div class="page-header">
        <h1 class="page-title">Create New User</h1>
        <p class="page-subtitle">Add a new user to the system</p>
    </div>

    <div class="row">
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">User Details</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.store') }}" method="post">
                        @csrf
                        @include('superadmin.users._fields')
                        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
                            <a href="{{ route('admin.index') }}" class="btn btn-outline">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Create User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
