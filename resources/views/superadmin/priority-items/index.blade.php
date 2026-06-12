@extends('superadmin.layouts.master')

@section('title')
    Priority Items
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="/superadmin">Dashboard</a></li>
    <li class="breadcrumb-item active">Priority Items</li>
@endsection

@section('style')
    <style>
        .priority-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            margin: 0;
            border: none;
            border-radius: 10px;
            padding: 0;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .priority-modal::backdrop {
            background: rgba(0, 0, 0, 0.5);
        }
        .priority-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .priority-modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        .priority-modal-body {
            padding: 1.5rem;
            max-height: 60vh;
            overflow-y: auto;
        }
        .priority-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            padding: 1rem 1.5rem;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        .priority-close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
            line-height: 1;
        }
        .priority-close-btn:hover {
            color: #111;
        }
        .priority-count-pill {
            display: inline-block;
            min-width: 24px;
            padding: 2px 8px;
            border-radius: 12px;
            background: #3b82f6;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
        }
        .row-checkbox {
            cursor: pointer;
            transform: scale(1.2);
        }
        #addItemsSearch {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            width: 100%;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }
    </style>
@endsection

@section('content')
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">Priority Items</h1>
            <p class="page-subtitle">A curated list of items to watch on the admin dashboard.</p>
        </div>
        <button type="button" id="btnOpenAddModal" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Add Items
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table" id="tblPriority" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Barcode</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Price</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    {{-- Add Items modal --}}
    <dialog id="addItemsModal" class="priority-modal">
        <div class="priority-modal-header">
            <h2>Add Items to Priority List</h2>
            <button type="button" class="priority-close-btn" data-close-modal>&times;</button>
        </div>
        <div class="priority-modal-body">
            <input type="text" id="addItemsSearch" placeholder="Search by item name or barcode…">

            <table class="table" id="tblAvailable" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAllAvailable" class="row-checkbox">
                        </th>
                        <th>Name</th>
                        <th>Barcode</th>
                        <th>Category</th>
                        <th class="text-end">Price</th>
                    </tr>
                </thead>
            </table>
        </div>
        <div class="priority-modal-footer">
            <span style="margin-right: auto; align-self: center; color: #6b7280;">
                Selected: <span id="selectedCount" class="priority-count-pill">0</span>
            </span>
            <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
            <button type="button" id="btnConfirmAdd" class="btn btn-primary" disabled>
                Add Selected
            </button>
        </div>
    </dialog>
@endsection

@section('script')
<script>
    $(function () {
        var dataUrl = @json(route('superadmin.priority-items.data'));
        var availableUrl = @json(route('superadmin.priority-items.available'));
        var addUrl = @json(route('superadmin.priority-items.add'));
        var removeUrlTemplate = @json(url('superadmin/priority-items')) + '/{id}/remove';
        var csrfToken = @json(csrf_token());

        // --- Currently-priority table ---
        var priorityTable = $('#tblPriority').DataTable({
            ajax: dataUrl,
            serverSide: true,
            processing: true,
            responsive: true,
            pageLength: 25,
            order: [[0, 'asc']],
            columns: [
                { data: 'name' },
                { data: 'barcode' },
                { data: 'category_name', orderable: false },
                { data: 'supplier_name', orderable: false },
                { data: 'cost', className: 'text-end' },
                { data: 'price', className: 'text-end' },
                { data: 'status_badge', orderable: false, searchable: false },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-end',
                    render: function (row) {
                        return '<button type="button" class="btn btn-sm btn-danger btn-remove-priority" data-id="' + row.id + '" data-name="' + escapeHtml(row.name) + '" title="Remove">'
                            + '<i class="fas fa-times"></i>'
                            + '</button>';
                    }
                }
            ],
            language: {
                emptyTable: 'No priority items yet. Click "Add Items" to start.',
                searchPlaceholder: 'Search priority items…',
                search: ''
            }
        });

        // --- Remove button handler (delegated) ---
        $('#tblPriority tbody').on('click', '.btn-remove-priority', function () {
            var id = $(this).data('id');
            var name = $(this).data('name');
            if (!confirm('Remove "' + name + '" from the priority list?')) return;

            $.ajax({
                url: removeUrlTemplate.replace('{id}', id),
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                success: function (resp) {
                    priorityTable.ajax.reload(null, false);
                },
                error: function () {
                    alert('Could not remove. Please retry.');
                }
            });
        });

        // --- Modal: open ---
        var modal = document.getElementById('addItemsModal');
        var availableTable = null;
        var selectedIds = new Set();

        $('#btnOpenAddModal').on('click', function () {
            selectedIds.clear();
            updateSelectedCount();
            $('#addItemsSearch').val('');
            $('#selectAllAvailable').prop('checked', false);

            if (!availableTable) {
                availableTable = $('#tblAvailable').DataTable({
                    ajax: availableUrl,
                    serverSide: true,
                    processing: true,
                    pageLength: 10,
                    order: [[1, 'asc']],
                    columns: [
                        {
                            data: 'id',
                            orderable: false,
                            searchable: false,
                            render: function (id) {
                                return '<input type="checkbox" class="row-checkbox available-checkbox" value="' + id + '">';
                            }
                        },
                        { data: 'name' },
                        { data: 'barcode' },
                        { data: 'category_name', orderable: false },
                        { data: 'price', className: 'text-end' }
                    ],
                    drawCallback: function () {
                        // Rebind click handlers on the freshly-rendered checkboxes
                        // (delegated events on tbody were unreliable across DataTables
                        // server-side redraws). Also re-tick based on the persistent
                        // selectedIds Set so selections survive pagination + search.
                        $('.available-checkbox').each(function () {
                            var checkbox = $(this);
                            var id = Number(checkbox.val());
                            checkbox.prop('checked', selectedIds.has(id));
                            checkbox.off('click.priority').on('click.priority', function () {
                                if (this.checked) {
                                    selectedIds.add(id);
                                } else {
                                    selectedIds.delete(id);
                                }
                                updateSelectedCount();
                            });
                        });
                    },
                    language: {
                        emptyTable: 'No items available to add.',
                        searchPlaceholder: 'Search…',
                        search: ''
                    },
                    dom: 'lrtip' // hide the built-in search box; we use our own
                });
            } else {
                availableTable.ajax.reload();
            }

            modal.showModal();
        });

        // --- Modal: close ---
        $('[data-close-modal]').on('click', function () { modal.close(); });
        modal.addEventListener('click', function (e) {
            // Close when clicking the ::backdrop. e.target equals the <dialog>
            // element itself only when the click landed on the backdrop; any
            // descendant click (including checkboxes) has e.target = descendant.
            if (e.target === modal) {
                modal.close();
            }
        });

        // --- Modal: search ---
        $('#addItemsSearch').on('input', function () {
            if (availableTable) {
                availableTable.search($(this).val()).draw();
            }
        });

        // --- Modal: select-all checkbox (per-row handlers are bound in drawCallback) ---
        $('#selectAllAvailable').on('change', function () {
            var checked = $(this).is(':checked');
            $('.available-checkbox').each(function () {
                $(this).prop('checked', checked);
                var id = Number($(this).val());
                if (checked) { selectedIds.add(id); } else { selectedIds.delete(id); }
            });
            updateSelectedCount();
        });

        function updateSelectedCount() {
            $('#selectedCount').text(selectedIds.size);
            $('#btnConfirmAdd').prop('disabled', selectedIds.size === 0);
        }

        // --- Modal: confirm add ---
        $('#btnConfirmAdd').on('click', function () {
            var ids = Array.from(selectedIds);
            if (ids.length === 0) return;

            $(this).prop('disabled', true).text('Adding…');
            $.ajax({
                url: addUrl,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                contentType: 'application/json',
                data: JSON.stringify({ item_ids: ids }),
                success: function (resp) {
                    modal.close();
                    priorityTable.ajax.reload(null, false);
                    selectedIds.clear();
                },
                error: function (xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Could not add items. Please retry.';
                    alert(msg);
                },
                complete: function () {
                    $('#btnConfirmAdd').prop('disabled', false).text('Add Selected');
                }
            });
        });

        function escapeHtml(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }
    });
</script>
@endsection
