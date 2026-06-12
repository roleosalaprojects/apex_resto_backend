@extends('superadmin.layouts.master')

@section('title')
    Users
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="/superadmin">Dashboard</a></li>
    <li class="breadcrumb-item active">Users</li>
@endsection

@section('content')
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">Users</h1>
            <p class="page-subtitle">Manage all system users</p>
        </div>
        <a href="{{ route('admin.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Add User
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table" id="tblUsers">
                <thead>
                    <tr>
                        <th style="width: 35%">Name</th>
                        <th style="width: 35%">Email</th>
                        <th style="width: 15%">Status</th>
                        <th style="width: 15%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td><strong>{{ $user->name }}</strong></td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @if ($user->status)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-danger">Deactivated</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group">
                                    @if ($user->status)
                                        {{-- Edit button hidden until the underlying edit/update flow is built;
                                             SuperAdmin\UserController::edit() / ::update() are currently empty. --}}
                                        <form action="{{ route('admin.destroy', $user) }}" method="post" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger btn-icon" title="Deactivate"
                                                    onclick="return confirm('Are you sure you want to deactivate this user?')">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        </form>
                                    @else
                                        <form action="{{ route('user.activate', $user) }}" method="post" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success btn-icon" title="Activate"
                                                    onclick="return confirm('Are you sure you want to activate this user?')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('script')
<script>
    $(function () {
        $("#tblUsers").DataTable({
            responsive: true,
            autoWidth: false,
            pageLength: 25,
            language: {
                search: "",
                searchPlaceholder: "Search users..."
            }
        });
    });
</script>
@endsection
