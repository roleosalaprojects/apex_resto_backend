@extends('layout.app')
@section('header')
    - View Inventory Count
@endsection
@section('title')
    IC #: {{$count->ic}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item "><a class="" href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item "><a class="" href="{{route('counts.index')}}">Inventory Counts</a></li>
    <li class="breadcrumb-item text-muted">IC #: {{$count->ic}}</li>
@endsection
@section('actions')
    @if ($access->invntry_read)
        <a href="{{route('print.ic', $count->id)}}" rel="noopener" target="_blank" class="btn btn-sm btn-active-color-danger btn-bg-light">Print</a>
    @endif
    @if ($access->invntry_update && $count->status < 2)
        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#finalizeModal">
            <i class="fas fa-check me-1"></i>Finalize Count
        </button>
    @endif
@endsection
@section('content')
    <div class="col">
        {{-- Details Card --}}
        <div class="card card-flush mb-7">
            <div class="card-header">
                <div class="card-title">Details</div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Store</label>
                        <div class="form-control-plaintext">{{$count->store?->name ?? 'N/A'}}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Status</label>
                        <div>
                            @php
                                $statusLabels = [0 => 'Draft', 1 => 'In Progress', 2 => 'Completed', 3 => 'Cancelled'];
                                $statusColors = [0 => 'secondary', 1 => 'warning', 2 => 'success', 3 => 'danger'];
                            @endphp
                            <span class="badge bg-{{ $statusColors[$count->status] ?? 'secondary' }}">
                                {{ $statusLabels[$count->status] ?? 'Unknown' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Created By</label>
                        <div class="form-control-plaintext">{{$count->creator?->name ?? 'N/A'}}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Date Created</label>
                        <div class="form-control-plaintext">{{\Carbon\Carbon::parse($count->created_at)->format('M d, Y (h:i A)')}}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Items Card --}}
        <div class="card card-flush">
            <div class="card-header">
                <div class="card-title">Items / Products</div>
            </div>
            <div class="card-body">
                @php
                    $totalItems = $count->lines->count();
                    $countedItems = $count->lines->whereNotNull('counted_qty')->count();
                    $varianceCount = 0;
                @endphp

                <table class="table table-hover" id="tableItems">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Unit</th>
                            <th class="text-end">Stocks</th>
                            <th class="text-end">Counted Qty</th>
                            <th class="text-end">Variance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($count->lines as $index => $line)
                            @php
                                $stock = $line->item_stock->where('store_id', $count->store_id)->first();
                                $systemStock = $stock ? (float)$stock->stock : 0;
                                $countedQty = $line->counted_qty !== null ? (float)$line->counted_qty : null;
                                $variance = $countedQty !== null ? $countedQty - $systemStock : null;
                                if ($variance !== null && $variance != 0) {
                                    $varianceCount++;
                                }
                            @endphp
                            <tr data-line-id="{{ $line->id }}">
                                <td>
                                    <span class="fw-bold">{{$line->item->name ?? 'N/A'}}</span>
                                    @if($line->item->barcode)
                                        <br><small class="text-muted">{{$line->item->barcode}}</small>
                                    @endif
                                </td>
                                <td>
                                    @if ($line->unit_id > 0 && $line->unit)
                                        {{$line->unit->name}}
                                        @php
                                            $itemUnit = $line->item_unit->where('unit_id', $line->unit_id)->first();
                                        @endphp
                                        @if ($itemUnit)
                                            <small class="text-muted">({{$itemUnit->qty}})</small>
                                        @endif
                                    @else
                                        {{ $line->item->type == 0 ? 'PCS' : 'KGS' }}
                                    @endif
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold">{{ number_format($systemStock, 2) }}</span>
                                </td>
                                <td class="text-end">
                                    @if ($count->status < 2 && $access->invntry_update)
                                        <input type="number"
                                               class="form-control form-control-sm text-end counted-qty-input"
                                               style="width: 100px; display: inline-block;"
                                               data-line-id="{{ $line->id }}"
                                               value="{{ $countedQty }}"
                                               step="0.01"
                                               min="0"
                                               placeholder="0.00">
                                    @elseif ($countedQty !== null)
                                        <span class="fw-bold text-primary">{{ number_format($countedQty, 2) }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <span class="variance-display" data-line-id="{{ $line->id }}" data-system-stock="{{ $systemStock }}">
                                        @if ($variance !== null)
                                            @if ($variance > 0)
                                                <span class="badge bg-success">+{{ number_format($variance, 2) }}</span>
                                            @elseif ($variance < 0)
                                                <span class="badge bg-danger">{{ number_format($variance, 2) }}</span>
                                            @else
                                                <span class="badge bg-secondary">0.00</span>
                                            @endif
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    @if ($count->status < 2 && $access->invntry_update)
                                        <button type="button" class="btn btn-sm btn-danger btn-icon btn-delete-line" data-line-id="{{ $line->id }}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($count->status < 2 && $access->invntry_update)
                    <tfoot>
                        <tr>
                            <td colspan="6">
                                <select id="itemSearch" class="form-select" data-placeholder="Select Item / Product" data-allow-clear="true">
                                    <option></option>
                                </select>
                            </td>
                        </tr>
                    </tfoot>
                    @endif
                </table>

                <div class="text-muted mt-3">
                    Showing {{ $totalItems }} item(s) | Counted: {{ $countedItems }} | Variances: <span id="varianceCount">{{ $varianceCount }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Finalize Modal --}}
    @if ($count->status < 2)
    <div class="modal fade" id="finalizeModal" tabindex="-1" aria-labelledby="finalizeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="finalizeModalLabel">Finalize Inventory Count</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if ($countedItems < $totalItems)
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> {{ $totalItems - $countedItems }} item(s) have not been counted yet.
                            You cannot finalize until all items are counted.
                        </div>
                    @else
                        <p>Are you sure you want to finalize this inventory count?</p>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>This action will:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Update stock for <strong>{{ $totalItems }}</strong> item(s)</li>
                                <li>Apply <strong id="modalVarianceCount">{{ $varianceCount }}</strong> variance(s) to the system</li>
                                <li>This action cannot be undone</li>
                            </ul>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    @if ($countedItems >= $totalItems)
                        <form action="{{ route('counts.finalize', $count->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check me-1"></i>Finalize & Update Stock
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection

@section('vendor-styles')
    <link rel="stylesheet" href="{{asset("assets/plugins/custom/datatables/datatables.bundle.css")}}">
@endsection

@section('vendor-scripts')
    <script src="{{asset("assets/plugins/custom/datatables/datatables.bundle.js")}}"></script>
@endsection

@section('scripts')
<script>
$(function() {
    // Initialize DataTable
    var table = $("#tableItems");
    table.DataTable({
        responsive: true,
        lengthChange: false,
        autoWidth: false,
        filter: false,
        searching: false,
        ordering: false,
        paging: false,
        info: false
    });

    @if ($count->status < 2 && $access->invntry_update)
    // Initialize Select2 for item search
    $("#itemSearch").select2({
        width: '100%',
        placeholder: 'Select Item / Product',
        allowClear: true,
        ajax: {
            url: '{{ route("items.select") }}',
            delay: 250,
            type: "get",
            dataType: 'json',
            data: function(params) {
                return { term: params.term };
            },
            processResults: function(data) {
                return { results: data };
            },
            cache: true
        }
    });

    // Add item when selected
    $("#itemSearch").on('select2:select', function(e) {
        var itemId = e.params.data.id;
        var itemName = e.params.data.text;

        $.ajax({
            url: '{{ route("counts.add-line", $count->id) }}',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            data: { item_id: itemId },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    toastr.error(response.message || 'Failed to add item');
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to add item');
            }
        });

        $(this).val(null).trigger('change');
    });

    // Delete item
    $(document).on('click', '.btn-delete-line', function() {
        var lineId = $(this).data('line-id');
        var row = $(this).closest('tr');

        if (confirm('Are you sure you want to remove this item?')) {
            $.ajax({
                url: '{{ route("counts.delete-line", $count->id) }}',
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                data: { line_id: lineId },
                success: function(response) {
                    if (response.success) {
                        table.DataTable().row(row).remove().draw();
                        toastr.success('Item removed');
                    } else {
                        toastr.error(response.message || 'Failed to remove item');
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to remove item');
                }
            });
        }
    });
    @endif

    // Handle counted quantity inline editing
    let debounceTimers = {};

    $(document).on('input', '.counted-qty-input', function() {
        var lineId = $(this).data('line-id');
        var value = $(this).val();
        var countedQty = parseFloat(value) || 0;
        var varianceDisplay = $(`.variance-display[data-line-id="${lineId}"]`);
        var systemStock = parseFloat(varianceDisplay.data('system-stock')) || 0;
        var variance = countedQty - systemStock;

        // Update variance display
        if (value === '' || value === null) {
            varianceDisplay.html('<span class="text-muted">-</span>');
        } else if (variance > 0) {
            varianceDisplay.html(`<span class="badge bg-success">+${variance.toFixed(2)}</span>`);
        } else if (variance < 0) {
            varianceDisplay.html(`<span class="badge bg-danger">${variance.toFixed(2)}</span>`);
        } else {
            varianceDisplay.html('<span class="badge bg-secondary">0.00</span>');
        }

        // Debounce the save
        clearTimeout(debounceTimers[lineId]);
        debounceTimers[lineId] = setTimeout(function() {
            saveCountedQty(lineId, value);
        }, 500);
    });

    function saveCountedQty(lineId, value) {
        $.ajax({
            url: '{{ route("counts.update-line", $count->id) }}',
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            data: JSON.stringify({
                line_id: lineId,
                counted_qty: value === '' ? null : parseFloat(value)
            }),
            success: function(response) {
                if (response.success) {
                    updateVarianceCount();
                }
            },
            error: function(error) {
                console.error('Error:', error);
            }
        });
    }

    function updateVarianceCount() {
        var varianceCount = 0;
        $('.counted-qty-input').each(function() {
            var value = $(this).val();
            if (value !== '' && value !== null) {
                var lineId = $(this).data('line-id');
                var varianceDisplay = $(`.variance-display[data-line-id="${lineId}"]`);
                var systemStock = parseFloat(varianceDisplay.data('system-stock')) || 0;
                var countedQty = parseFloat(value) || 0;
                if (countedQty !== systemStock) {
                    varianceCount++;
                }
            }
        });
        $('#varianceCount').text(varianceCount);
        $('#modalVarianceCount').text(varianceCount);
    }
});
</script>
@endsection
