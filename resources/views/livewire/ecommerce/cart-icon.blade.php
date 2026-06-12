<div>
    <a href="{{ route('shops.cart') }}" class="btn btn-icon position-relative" style="background: rgba(255,255,255,0.2); border-radius: 10px;">
        <i class="ki-duotone ki-handcart fs-2 text-white"><span class="path1"></span><span class="path2"></span></i>
        @if($count > 0)
            <span class="position-absolute top-0 start-100 translate-middle badge badge-circle badge-sm qb-badge-cart">
                {{ $count }}
            </span>
        @endif
    </a>
</div>
