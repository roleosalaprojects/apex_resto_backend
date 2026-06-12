<div class="d-flex justify-content-end gap-2">
    <button type="button"
            class="btn btn-sm btn-success js-clear-btn"
            data-sale-id="{{ $sale->id }}"
            data-sale-son="{{ $sale->son }}"
            data-cheque-no="{{ $sale->reference_number }}"
            data-amount="{{ number_format((float) $sale->bank_amount, 2) }}"
            data-customer="{{ $sale->customer?->name ?? '—' }}">
        <i class="ki-duotone ki-check fs-5"><span class="path1"></span><span class="path2"></span></i>
        Cleared
    </button>
    <button type="button"
            class="btn btn-sm btn-danger js-bounce-btn"
            data-sale-id="{{ $sale->id }}"
            data-sale-son="{{ $sale->son }}"
            data-cheque-no="{{ $sale->reference_number }}"
            data-amount="{{ number_format((float) $sale->total, 2) }}"
            data-customer="{{ $sale->customer?->name ?? '—' }}">
        <i class="ki-duotone ki-cross fs-5"><span class="path1"></span><span class="path2"></span></i>
        Bounced
    </button>
</div>
