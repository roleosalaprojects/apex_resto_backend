<div class="card">
    <div class="card-body">
        <div class="form-group mb-5">
            <label for="name" class="form-label required">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name', $station->name) }}" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}" placeholder="e.g. Hot Kitchen">
            <span class="text-danger">{{ $errors->first('name') }}</span>
        </div>
        <div class="form-group mb-5">
            <label for="store_id" class="form-label">Store</label>
            <select name="store_id" id="store_id" class="form-select">
                <option value="">— None —</option>
                @foreach($stores as $store)
                    <option value="{{ $store->id }}" {{ old('store_id', $station->store_id) == $store->id ? 'selected' : '' }}>{{ $store->name }}</option>
                @endforeach
            </select>
        </div>
        @if($station->exists)
            <div class="form-check mb-5">
                <input type="checkbox" class="form-check-input" name="status" id="status" value="1" {{ $station->status ? 'checked' : '' }}>
                <label for="status" class="form-check-label">Active</label>
            </div>
        @endif
    </div>
</div>
