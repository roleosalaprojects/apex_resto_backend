@php
    /** @var \App\Models\Settings\ColorPalette $palette */
    $colorFields = [
        'primary' => 'Primary',
        'secondary' => 'Secondary',
        'accent' => 'Accent',
        'on_primary' => 'Text on Primary',
        'on_secondary' => 'Text on Secondary',
    ];
@endphp

<style>
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    .form-row > div { display: flex; flex-direction: column; gap: 4px; }
    .form-row label { font-weight: 500; font-size: 0.85rem; color: #475569; }
    .form-row input { padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; }
    .color-row { display: flex; gap: 8px; align-items: center; }
    .color-row input[type=color] { width: 48px; height: 36px; padding: 0; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; }
    .color-row input[type=text] { flex: 1; font-family: monospace; }
    .preview-pane { padding: 1rem; border-radius: 8px; margin-top: 1rem; }
    .preview-btn { display: inline-block; padding: 6px 14px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; }
    .invalid-feedback { color: #dc2626; font-size: 0.8rem; }
</style>

<div class="form-row">
    <div>
        <label for="key">Key (slug)</label>
        <input type="text" id="key" name="key" value="{{ old('key', $palette->key) }}" required pattern="[a-z0-9_\-]+">
        @error('key') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div>
        <label for="label">Label</label>
        <input type="text" id="label" name="label" value="{{ old('label', $palette->label) }}" required>
        @error('label') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-row">
    @foreach ($colorFields as $field => $title)
        <div>
            <label for="{{ $field }}">{{ $title }}</label>
            <div class="color-row">
                <input type="color" data-color-picker="{{ $field }}" value="{{ old($field, $palette->$field) }}">
                <input type="text" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $palette->$field) }}" required pattern="^#[0-9A-Fa-f]{6}$" placeholder="#1858fd">
            </div>
            @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endforeach
</div>

<div class="form-row">
    <div>
        <label for="sort_order">Sort order</label>
        <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $palette->sort_order ?? 0) }}" min="0" max="9999">
        @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div>
        <label>Status</label>
        <div>
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $palette->is_active ?? true) ? 'checked' : '' }}>
            <label for="is_active" style="font-weight: 400">Active (tenants can pick this palette)</label>
        </div>
    </div>
</div>

<div class="preview-pane" id="palettePreview">
    <div style="font-size: 0.75rem; opacity: 0.7; margin-bottom: 0.5rem;">Live preview</div>
    <div style="display: flex; gap: 12px; align-items: center;">
        <span class="preview-btn" data-preview-primary>Primary button</span>
        <span class="preview-btn" data-preview-secondary>Secondary button</span>
        <span data-preview-accent style="font-weight:600">Accent text</span>
    </div>
</div>

<div style="margin-top: 1.5rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
    <a href="{{ route('superadmin.color-palettes.index') }}" class="btn">Cancel</a>
    <button type="submit" class="btn btn-primary">Save palette</button>
</div>

<script>
    (function () {
        const fields = ['primary', 'secondary', 'accent', 'on_primary', 'on_secondary'];

        function syncPair(field) {
            const picker = document.querySelector('[data-color-picker="' + field + '"]');
            const text = document.getElementById(field);
            if (!picker || !text) return;
            picker.addEventListener('input', function () { text.value = picker.value; refreshPreview(); });
            text.addEventListener('input', function () {
                if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) picker.value = text.value;
                refreshPreview();
            });
        }

        function refreshPreview() {
            const get = (f) => document.getElementById(f)?.value || '#000000';
            const pane = document.getElementById('palettePreview');
            pane.style.background = '#ffffff';
            const primary = document.querySelector('[data-preview-primary]');
            primary.style.background = get('primary');
            primary.style.color = get('on_primary');
            const secondary = document.querySelector('[data-preview-secondary]');
            secondary.style.background = get('secondary');
            secondary.style.color = get('on_secondary');
            const accent = document.querySelector('[data-preview-accent]');
            accent.style.color = get('accent');
        }

        fields.forEach(syncPair);
        refreshPreview();
    })();
</script>
