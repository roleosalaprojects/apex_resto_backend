<div class="card">
    <div class="card-body">
        <div class="form-group mb-5">
            <label for="name" class="form-label required">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name', $table->name) }}" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}">
            <span class="text-danger">{{ $errors->first('name') }}</span>
        </div>
        <div class="row mb-5">
            <div class="col-md-4">
                <label for="number" class="form-label">Number</label>
                <input type="text" name="number" id="number" value="{{ old('number', $table->number) }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label for="area" class="form-label">Area</label>
                <input type="text" name="area" id="area" value="{{ old('area', $table->area) }}" class="form-control" placeholder="e.g. Patio">
            </div>
            <div class="col-md-4">
                <label for="seats" class="form-label required">Seats</label>
                <input type="number" min="1" name="seats" id="seats" value="{{ old('seats', $table->seats ?: 2) }}" class="form-control">
            </div>
        </div>
        <div class="form-group mb-5">
            <label for="store_id" class="form-label">Store</label>
            <select name="store_id" id="store_id" class="form-select">
                <option value="">— None —</option>
                @foreach($stores as $store)
                    <option value="{{ $store->id }}" {{ old('store_id', $table->store_id) == $store->id ? 'selected' : '' }}>{{ $store->name }}</option>
                @endforeach
            </select>
        </div>
        @if($table->exists)
            <div class="form-group mb-5">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="0" {{ $table->status == 0 ? 'selected' : '' }}>Available</option>
                    <option value="1" {{ $table->status == 1 ? 'selected' : '' }}>Occupied</option>
                    <option value="2" {{ $table->status == 2 ? 'selected' : '' }}>Reserved</option>
                    <option value="3" {{ $table->status == 3 ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
        @endif
    </div>
</div>
