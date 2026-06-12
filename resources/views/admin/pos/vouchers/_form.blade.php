<div class="row">
    <div class="col-md-6">
        <div class="form-group mb-5">
            <label class="form-label">Voucher Code</label>
            <div class="input-group">
                <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                       value="{{ old('code', $voucher->code) }}" placeholder="Leave blank to auto-generate" maxlength="50">
                <button type="button" class="btn btn-outline-secondary" id="generateCode">
                    <i class="fas fa-sync-alt"></i> Generate
                </button>
            </div>
            @error('code')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            <div class="form-text">Unique code that customers will use at checkout</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group mb-5">
            <label class="form-label required">Voucher Name</label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $voucher->name) }}" placeholder="e.g. Summer Sale Discount" required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group mb-5">
            <label class="form-label required">Discount Amount</label>
            <div class="input-group">
                <span class="input-group-text">₱</span>
                <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror"
                       value="{{ old('amount', $voucher->amount) }}" step="0.01" min="0" required>
            </div>
            @error('amount')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            <div class="form-text">Fixed discount amount to be applied</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group mb-5">
            <label class="form-label">Minimum Cart Amount</label>
            <div class="input-group">
                <span class="input-group-text">₱</span>
                <input type="number" name="minimum_amount" class="form-control @error('minimum_amount') is-invalid @enderror"
                       value="{{ old('minimum_amount', $voucher->minimum_amount ?? 0) }}" step="0.01" min="0">
            </div>
            @error('minimum_amount')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            <div class="form-text">Minimum cart total required to use voucher (0 = no minimum)</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group mb-5">
            <label class="form-label required">Maximum Uses</label>
            <input type="number" name="max_uses" class="form-control @error('max_uses') is-invalid @enderror"
                   value="{{ old('max_uses', $voucher->max_uses ?? 1) }}" min="1" required>
            @error('max_uses')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">How many times this voucher can be used</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group mb-5">
            <label class="form-label">Store Restriction</label>
            <select name="store_id" class="form-select @error('store_id') is-invalid @enderror">
                <option value="">All Stores</option>
                @foreach($stores as $store)
                    <option value="{{ $store->id }}" {{ old('store_id', $voucher->store_id) == $store->id ? 'selected' : '' }}>
                        {{ $store->name }}
                    </option>
                @endforeach
            </select>
            @error('store_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">Leave empty to allow usage at all stores</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group mb-5">
            <label class="form-label required">Expiration Date</label>
            <input type="text" name="expires_at" id="expires_at" class="form-control @error('expires_at') is-invalid @enderror"
                   value="{{ old('expires_at', $voucher->expires_at ? $voucher->expires_at->format('Y-m-d H:i') : '') }}"
                   placeholder="Select date and time" required>
            @error('expires_at')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group mb-5">
            <div class="form-check form-switch form-check-custom form-check-solid">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                       {{ old('is_active', $voucher->is_active ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">
                    Active
                </label>
            </div>
            <div class="form-text">Inactive vouchers cannot be used at checkout</div>
        </div>
    </div>
</div>

@if($voucher->exists)
<div class="row">
    <div class="col-12">
        <div class="alert alert-info d-flex align-items-center">
            <i class="ki-duotone ki-information-5 fs-2 me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
            <div>
                <strong>Usage:</strong> {{ $voucher->used_count }} / {{ $voucher->max_uses }} times used
                @if($voucher->isExpired())
                    <span class="badge badge-danger ms-2">Expired</span>
                @elseif(!$voucher->hasUsesRemaining())
                    <span class="badge badge-warning ms-2">Used Up</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endif
