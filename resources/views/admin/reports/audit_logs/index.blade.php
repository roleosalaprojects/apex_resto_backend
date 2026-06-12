@extends('layout.app')
@section('header')
    - Audit Trail
@endsection
@section('title')
    Audit Trail
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.receipts') }}">Reports</a></li>
    <li class="breadcrumb-item text-muted">Audit Trail</li>
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">System Activity Log</h4>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Event Type</label>
                            <select id="filterEvent" class="form-select">
                                <option value="">All Events</option>
                                <option value="created">Created</option>
                                <option value="updated">Updated</option>
                                <option value="deleted">Deleted</option>
                                <option value="voided">Voided</option>
                                <option value="refunded">Refunded</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Source</label>
                            <select id="filterSource" class="form-select">
                                <option value="">All Sources</option>
                                <option value="web">Web (admin)</option>
                                <option value="openclaw">OpenClaw (bot)</option>
                                <option value="mobile">Mobile</option>
                                <option value="pos">POS</option>
                                <option value="console">Console (artisan)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">From Date</label>
                            <input type="date" id="filterDateFrom" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To Date</label>
                            <input type="date" id="filterDateTo" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-12 d-flex align-items-end">
                            <button type="button" id="filterBtn" class="btn btn-primary">Filter</button>
                            <button type="button" id="resetBtn" class="btn btn-secondary ms-2">Reset</button>
                        </div>
                    </div>
                    <div id="logsContainer">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
<script>
    $(function() {
        function loadLogs(page = 1) {
            const params = new URLSearchParams();
            const event = $('#filterEvent').val();
            const source = $('#filterSource').val();
            const dateFrom = $('#filterDateFrom').val();
            const dateTo = $('#filterDateTo').val();

            if (event) params.append('event', event);
            if (source) params.append('source', source);
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            if (page && page > 1) params.append('page', page);

            $.get("{{ route('audit_logs.table') }}?" + params.toString(), function(data) {
                $('#logsContainer').html(data);
            });
        }

        loadLogs();

        $('#filterBtn').click(function() {
            loadLogs();
        });

        $('#resetBtn').click(function() {
            $('#filterEvent').val('');
            $('#filterSource').val('');
            $('#filterDateFrom').val('');
            $('#filterDateTo').val('');
            loadLogs();
        });

        // Pagination links live inside the AJAX-loaded partial. They point at
        // the table endpoint (?page=N), which is fine for refetching but
        // would cause a full-page navigation to the bare partial if the
        // browser handled the click. Intercept and refetch via AJAX so the
        // user stays on the styled index page.
        $('#logsContainer').on('click', '.pagination a', function(e) {
            e.preventDefault();
            const href = $(this).attr('href');
            if (!href) return;
            const url = new URL(href, window.location.origin);
            const page = parseInt(url.searchParams.get('page'), 10) || 1;
            loadLogs(page);
        });
    });
</script>
@endsection
