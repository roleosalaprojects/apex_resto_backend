@extends('layout.app')
@section('header')
    - Pending Cheques
@endsection
@section('title')
    Pending Cheques
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('banks.index') }}">Banking</a></li>
    <li class="breadcrumb-item text-muted">Pending Cheques</li>
@endsection
@section('content')
    {{-- 'success' + 'error' flashes are rendered by the global
         layout/messages.blade.php — only 'warning' needs a local render. --}}
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title fw-bold">Cheques awaiting clearing</h3>
            <div class="card-toolbar">
                <span class="text-muted fs-7">Mark each cheque cleared when the drawee bank confirms, or bounced if it fails. Cheques in red are over 30 days old.</span>
            </div>
        </div>
        <div class="card-body">
            <table id="pendingChequesTable" class="table table-row-bordered table-row-gray-200 align-middle gy-4 w-100">
                <thead>
                    <tr class="fw-bold text-muted">
                        <th>Sale</th>
                        <th>Customer</th>
                        <th>Cheque #</th>
                        <th>Drawee Bank</th>
                        <th class="text-end">Amount</th>
                        <th class="text-center">Days Out</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    {{-- Mark Cleared modal --}}
    <div class="modal fade" id="clearChequeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="clearChequeForm" action="">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Mark Cheque Cleared</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-light-success d-flex align-items-center mb-5">
                            <i class="ki-duotone ki-check-circle fs-2x text-success me-3"><span class="path1"></span><span class="path2"></span></i>
                            <div>
                                <div class="fw-semibold js-summary"></div>
                                <div class="text-muted fs-7">Once cleared, the bank balance updates immediately.</div>
                            </div>
                        </div>

                        <div class="mb-5">
                            <label class="form-label fw-semibold required">Date Cleared</label>
                            <input type="date" name="cleared_date" class="form-control" required value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Clearing Reference (optional)</label>
                            <input type="text" name="clearing_reference" class="form-control" maxlength="120" placeholder="Bank's clearing reference, if any">
                            <div class="form-text">Defaults to a generated reference if blank.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Mark Cleared</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Mark Bounced modal --}}
    <div class="modal fade" id="bounceChequeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="bounceChequeForm" action="">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-danger">Mark Cheque Bounced</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-light-danger d-flex align-items-start mb-5">
                            <i class="ki-duotone ki-cross-circle fs-2x text-danger me-3"><span class="path1"></span><span class="path2"></span></i>
                            <div>
                                <div class="fw-semibold js-summary"></div>
                                <div class="text-muted fs-7 mt-1">
                                    The customer keeps the goods but is charged the amount via their credit ledger.
                                    No bank balance change — the cheque never cleared.
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Note (optional)</label>
                            <textarea name="bounce_note" class="form-control" rows="3" maxlength="500" placeholder="e.g. insufficient funds, account closed"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Mark Bounced</button>
                    </div>
                </form>
            </div>
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
            const table = $('#pendingChequesTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: "{{ route('pending-cheques.table') }}",
                columns: [
                    { data: 'son', name: 'son' },
                    { data: 'customer_name', name: 'customer.name' },
                    { data: 'reference_number', name: 'reference_number' },
                    { data: 'bank_name', name: 'bank.account_name' },
                    { data: 'amount_formatted', name: 'bank_amount', className: 'text-end' },
                    { data: 'days_outstanding', name: 'created_at', className: 'text-center' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ],
                order: [[5, 'desc']],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],
            });

            const $clearModal = $('#clearChequeModal');
            const $clearForm = $('#clearChequeForm');
            const $bounceModal = $('#bounceChequeModal');
            const $bounceForm = $('#bounceChequeForm');

            const clearAction = (id) => "{{ url('admin/pending-cheques') }}/" + id + "/clear";
            const bounceAction = (id) => "{{ url('admin/pending-cheques') }}/" + id + "/bounce";

            // Delegated handlers so dynamically-added rows from
            // DataTables redraws keep working.
            $('#pendingChequesTable tbody').on('click', '.js-clear-btn', function () {
                const btn = $(this);
                const summary = `Cheque #${btn.data('cheque-no') || '—'} from ${btn.data('customer')} · ₱${btn.data('amount')} (Sale ${btn.data('sale-son')})`;
                $clearModal.find('.js-summary').text(summary);
                $clearForm.attr('action', clearAction(btn.data('sale-id')));
                $clearModal.modal('show');
            });

            $('#pendingChequesTable tbody').on('click', '.js-bounce-btn', function () {
                const btn = $(this);
                const summary = `Cheque #${btn.data('cheque-no') || '—'} from ${btn.data('customer')} · ₱${btn.data('amount')} (Sale ${btn.data('sale-son')})`;
                $bounceModal.find('.js-summary').text(summary);
                $bounceForm.attr('action', bounceAction(btn.data('sale-id')));
                $bounceModal.modal('show');
            });
        });
    </script>
@endsection
