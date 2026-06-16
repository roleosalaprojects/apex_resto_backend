{{-- Shared date-range filter + CSV export for Annex F reports. --}}
<form method="GET" class="row g-3 align-items-end mb-5">
    <div class="col-auto">
        <label class="form-label">From</label>
        <input type="date" name="startDate" value="{{ $start }}" class="form-control">
    </div>
    <div class="col-auto">
        <label class="form-label">To</label>
        <input type="date" name="endDate" value="{{ $end }}" class="form-control">
    </div>
    @isset($type)
        <div class="col-auto">
            <label class="form-label">Class</label>
            <select name="type" class="form-select">
                @foreach(['sc' => 'Senior Citizen', 'pwd' => 'PWD', 'solo_parent' => 'Solo Parent', 'naac' => 'NAAC'] as $value => $label)
                    <option value="{{ $value }}" {{ $type === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endisset
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Apply</button>
        <a href="{{ route('reports.bir.annexf.export', $exportKey) }}?startDate={{ $start }}&endDate={{ $end }}{{ isset($type) ? '&type='.$type : '' }}" class="btn btn-light-success">Export CSV</a>
    </div>
</form>
