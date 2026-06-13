<div class="card">
    <div class="card-body">
        <div class="row mb-5">
            <div class="col-md-6">
                <label for="name" class="form-label required">Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $reservation->name) }}" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}">
                <span class="text-danger">{{ $errors->first('name') }}</span>
            </div>
            <div class="col-md-6">
                <label for="phone" class="form-label">Phone</label>
                <input type="text" name="phone" id="phone" value="{{ old('phone', $reservation->phone) }}" class="form-control">
            </div>
        </div>
        <div class="row mb-5">
            <div class="col-md-4">
                <label for="party_size" class="form-label required">Party Size</label>
                <input type="number" min="1" name="party_size" id="party_size" value="{{ old('party_size', $reservation->party_size ?: 1) }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label for="reserved_at" class="form-label required">Reserved At</label>
                <input type="datetime-local" name="reserved_at" id="reserved_at" value="{{ old('reserved_at', optional($reservation->reserved_at)->format('Y-m-d\TH:i')) }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label for="duration_minutes" class="form-label">Duration (min)</label>
                <input type="number" min="1" name="duration_minutes" id="duration_minutes" value="{{ old('duration_minutes', $reservation->duration_minutes ?: 90) }}" class="form-control">
            </div>
        </div>
        <div class="row mb-5">
            <div class="col-md-6">
                <label for="table_id" class="form-label">Table</label>
                <select name="table_id" id="table_id" class="form-select">
                    <option value="">— Unassigned —</option>
                    @foreach($tables as $t)
                        <option value="{{ $t->id }}" {{ old('table_id', $reservation->table_id) == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            @if($reservation->exists)
                <div class="col-md-6">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        @foreach(\App\Models\Restaurant\Reservation::STATUSES as $status)
                            <option value="{{ $status }}" {{ $reservation->status === $status ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
        <div class="form-group mb-5">
            <label for="notes" class="form-label">Notes</label>
            <textarea name="notes" id="notes" class="form-control" rows="2">{{ old('notes', $reservation->notes) }}</textarea>
        </div>
    </div>
</div>
