@props([
    'name',
    'title',
    'value' => null,
    'required' => false
])

<div class="form-group mb-6 fv-row">
    <label for="{{ $name }}" class="form-label {{ $required ? 'required' : '' }}">{{ $title }}</label>
    <input {{ $attributes }} class="form-control" id="{{ $name }}" name="{{ $name }}" value="{{ $value }}">
    {{ $slot }}
</div>