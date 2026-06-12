@extends('layout.app')
@section('header')
    - SMS Logs
@endsection
@section('title')
    SMS Logs
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">SMS Logs</li>
@endsection
@section('styles')
    <style>
        /* Custom variant for the "processing" status — VeroSMS's
           in-flight state. Bootstrap's built-in info colour family is
           a close-enough fit visually; this just maps the badge class
           name our model returns. */
        .badge-light-processing { background-color: #cffafe; color: #155e75; }
    </style>
@endsection
@section('content')
    {{-- Flash banners are rendered by layout/messages.blade.php. --}}

    <div class="card mb-5">
        <div class="card-body py-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fs-7 fw-semibold">Status</label>
                    <select class="form-select form-select-sm" id="filterStatus">
                        <option value="">All</option>
                        <option value="sent">Sent (not yet polled)</option>
                        <option value="delivered">Delivered</option>
                        <option value="processing">Processing</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-7 fw-semibold">Type</label>
                    <select class="form-select form-select-sm" id="filterType">
                        <option value="">All</option>
                        <option value="otp_register">OTP — Register</option>
                        <option value="order_update">Order Update</option>
                        <option value="general">General</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-7 fw-semibold">Phone (substring)</label>
                    <input type="text" id="filterPhone" class="form-control form-control-sm" placeholder="09171234567">
                </div>
                <div class="col-md-2">
                    <button id="btnReset" class="btn btn-sm btn-secondary w-100">
                        <i class="ki-outline ki-arrows-circle fs-6"></i> Reset
                    </button>
                </div>
                <div class="col-md-3">
                    <button id="btnPollAll" class="btn btn-sm btn-primary w-100" title="Background-poll every 'sent' row that hasn't been polled in the last 5 minutes">
                        <i class="ki-outline ki-loading fs-6"></i> Poll Pending
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="smsLogsTable" class="table table-row-bordered table-row-gray-200 align-middle gy-4 w-100">
                <thead>
                    <tr class="fw-bold text-muted">
                        <th>Sent</th>
                        <th>Phone</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>VeroSMS ID</th>
                        <th>Last Checked</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
@endsection

@section('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}">
@endsection
@section('vendor-scripts')
    <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
@endsection
@section('scripts')
    <script>
        $(function () {
            const tableUrl = @json(route('sms-logs.table'));
            const refreshBase = @json(url('admin/sms-logs'));
            const bulkPollUrl = @json(route('sms-logs.bulk-poll'));
            const csrf = @json(csrf_token());

            const table = $('#smsLogsTable').DataTable({
                processing: true,
                // Server-side pagination — payload is constant ~25
                // rows regardless of how many millions of historical
                // rows live in the table. Crucial once we blast.
                serverSide: true,
                deferRender: true,
                ajax: {
                    url: tableUrl,
                    data: function (d) {
                        d.status = $('#filterStatus').val();
                        d.type = $('#filterType').val();
                        d.phone = $('#filterPhone').val();
                    },
                },
                columns: [
                    { data: 'created_human', name: 'created_at' },
                    { data: 'phone', name: 'phone' },
                    { data: 'type', name: 'type' },
                    { data: 'status_badge', name: 'status', orderable: false },
                    { data: 'sms_id', name: 'sms_id', defaultContent: '<span class="text-muted">—</span>' },
                    { data: 'last_checked_human', name: 'last_checked_at', orderable: false },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ],
                order: [[0, 'desc']],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],
                searching: false, // we provide our own filters above
            });

            // Filter triggers — debounce phone search so we don't redraw
            // on every keystroke.
            let phoneDebounce = null;
            $('#filterPhone').on('input', function () {
                clearTimeout(phoneDebounce);
                phoneDebounce = setTimeout(() => table.ajax.reload(null, false), 300);
            });
            $('#filterStatus, #filterType').on('change', () => table.ajax.reload(null, false));
            $('#btnReset').on('click', function () {
                $('#filterStatus').val('');
                $('#filterType').val('');
                $('#filterPhone').val('');
                table.ajax.reload(null, false);
            });

            // Delegated handler so DataTables redraws don't lose the
            // refresh button wiring. Refresh updates the single row in
            // place — no full table reload — so it's cheap even when
            // thousands of rows exist downstream.
            $('#smsLogsTable tbody').on('click', '.js-refresh-status', async function () {
                const btn = $(this);
                const logId = btn.data('log-id');
                const originalText = btn.html();
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Polling…');

                try {
                    const res = await fetch(`${refreshBase}/${logId}/refresh-status`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                    });
                    const body = await res.json().catch(() => ({}));

                    if (res.ok && body.success) {
                        // Splice the new status badge into the same row
                        // — DataTable.row() finds by data attribute we
                        // set via setRowAttr on the server side.
                        const $row = btn.closest('tr');
                        $row.find('td').eq(3).html(body.data.status_badge_html);
                        if (body.data.last_checked_at) {
                            $row.find('td').eq(5).text(body.data.last_checked_at);
                        }
                        btn.prop('disabled', false).html(originalText);
                    } else {
                        alert(body.message || 'Could not refresh status.');
                        btn.prop('disabled', false).html(originalText);
                    }
                } catch (_) {
                    alert('Network error.');
                    btn.prop('disabled', false).html(originalText);
                }
            });

            // Bulk: dispatch the background poller for every pending
            // row. Up to 500/call. Reload the table once dispatched —
            // the worker processes async so the page won't show
            // results immediately; admin can refresh again in ~30s.
            $('#btnPollAll').on('click', async function () {
                const btn = $(this);
                const originalText = btn.html();
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Queuing…');

                try {
                    const res = await fetch(bulkPollUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    const body = await res.json().catch(() => ({}));
                    alert(body.message || 'Queued.');
                } catch (_) {
                    alert('Network error.');
                } finally {
                    btn.prop('disabled', false).html(originalText);
                }
            });
        });
    </script>
@endsection
