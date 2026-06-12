@extends('layout.app')
@section('header')
    - Employee Schedules
@endsection
@section('title')
    Employee Schedules
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">Schedules</li>
@endsection
@section('content')
    @if(session('msg'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('msg') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col">
            <div class="card mb-5">
                <div class="card-header">
                    <h4 class="card-title">Filters</h4>
                </div>
                <div class="card-body">
                    <form id="filterForm" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search Employee</label>
                            <input type="text" name="search" id="search" class="form-control" placeholder="Search by name...">
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Employee Schedules</h4>
                </div>
                <div class="card-body">
                    <div id="tableContainer">
                        @include('admin.employees.schedules.table')
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
<script>
$(function() {
    var searchTimeout;

    function loadTable(url) {
        url = url || '{{ route("schedules.table") }}';
        $.get(url, $('#filterForm').serialize(), function(data) {
            $('#tableContainer').html(data);
        });
    }

    // Load initial data
    loadTable();

    // Search with debounce
    $('#search').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            loadTable();
        }, 300);
    });

    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        loadTable($(this).attr('href'));
    });
});
</script>
@endsection
