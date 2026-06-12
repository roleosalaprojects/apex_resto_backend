@extends('layout.app')
@section('header')
    - Edit Attendance Record
@endsection
@section('title')
    Edit Attendance Record
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('attendance.index') }}">Attendance</a></li>
    <li class="breadcrumb-item text-muted">Edit Record</li>
@endsection
@section('content')
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Edit Attendance Record</h4>
                </div>
                <form action="{{ route('attendance.update', $attendance) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <label class="form-label required">Employee</label>
                                <select name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                                    <option value="">Select Employee</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}" {{ old('user_id', $attendance->user_id) == $employee->id ? 'selected' : '' }}>
                                            {{ $employee->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('user_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Store</label>
                                <select name="store_id" class="form-select @error('store_id') is-invalid @enderror" required>
                                    <option value="">Select Store</option>
                                    @foreach($stores as $store)
                                        <option value="{{ $store->id }}" {{ old('store_id', $attendance->store_id) == $store->id ? 'selected' : '' }}>
                                            {{ $store->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('store_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-5">
                            <div class="col-md-4">
                                <label class="form-label required">Date</label>
                                <input type="date" name="date" class="form-control @error('date') is-invalid @enderror" value="{{ old('date', $attendance->date->format('Y-m-d')) }}" required>
                                @error('date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Time In</label>
                                <input type="time" name="time_in" class="form-control @error('time_in') is-invalid @enderror" value="{{ old('time_in', $attendance->time_in?->format('H:i')) }}">
                                @error('time_in')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Time Out</label>
                                <input type="time" name="time_out" class="form-control @error('time_out') is-invalid @enderror" value="{{ old('time_out', $attendance->time_out?->format('H:i')) }}">
                                @error('time_out')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-5">
                            <div class="col-md-6">
                                <label class="form-label required">Status</label>
                                <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                    <option value="present" {{ old('status', $attendance->status) == 'present' ? 'selected' : '' }}>Present</option>
                                    <option value="absent" {{ old('status', $attendance->status) == 'absent' ? 'selected' : '' }}>Absent</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-5">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control @error('remarks') is-invalid @enderror" rows="3" placeholder="Optional remarks...">{{ old('remarks', $attendance->remarks) }}</textarea>
                            @error('remarks')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('attendance.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
