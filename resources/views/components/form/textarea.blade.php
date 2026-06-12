@props([
    'name',
    'value' => null,
    'required' => false
])

<div class="mb-6 form-group fv-row">
    <label for="{{ $name }}" class="form-label {{ $required ? 'required' : '' }}">Description</label>
    <textarea name="{{ $name }}" id="{{ $name }}" class="form-control" data-kt-autosize="true" {{ $attributes }}>{{ $value }}</textarea>
</div>