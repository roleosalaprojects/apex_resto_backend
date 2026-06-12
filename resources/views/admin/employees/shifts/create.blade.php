@extends('layout.app')
@section('header')
    - Clock In
@endsection
@section('title')
    Clock In
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('shifts.index') }}">Shifts</a></li>
    <li class="breadcrumb-item text-muted">Clock In</li>
@endsection
@section('content')
    @if($activeShift)
        <div class="alert alert-warning">
            You already have an active shift. Please <a href="{{ route('shifts.show', $activeShift) }}">clock out</a> first.
        </div>
    @else
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Start Your Shift</h4>
                    </div>
                    <form action="{{ route('shifts.store') }}" method="POST">
                        @csrf
                        <div class="card-body">
                            @if($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <div class="mb-3">
                                <label class="form-label">Store</label>
                                <select name="store_id" class="form-select">
                                    <option value="">Select Store (Optional)</option>
                                    @foreach($stores as $store)
                                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Terminal</label>
                                <select name="pos_id" class="form-select">
                                    <option value="">Select Terminal (Optional)</option>
                                    @foreach($terminals as $terminal)
                                        <option value="{{ $terminal->id }}">{{ $terminal->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Starting Cash <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="starting_cash" class="form-control" value="{{ old('starting_cash', 0) }}" required>
                                <small class="text-muted">Enter the amount of cash in the drawer at the start of your shift.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="{{ route('shifts.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Clock In</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
