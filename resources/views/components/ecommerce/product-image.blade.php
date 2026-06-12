{{-- Product image with a branded fallback tile. Renders the real <img>
     only when the file is actually present on disk; otherwise shows the
     item's category emoji on a brand-tinted gradient so missing uploads
     never surface as broken-image question marks. --}}
@props(['item', 'emojiClass' => 'fs-3x'])
@php($displayImageUrl = $item->displayImageUrl())
@if($displayImageUrl)
    <img src="{{ $displayImageUrl }}" alt="{{ $item->name }}" {{ $attributes }}>
@else
    <div {{ $attributes->merge(['class' => 'qb-img-placeholder']) }} role="img" aria-label="{{ $item->name }}">
        <span class="{{ $emojiClass }}">{{ $item->category?->icon ?: '🛒' }}</span>
    </div>
@endif
