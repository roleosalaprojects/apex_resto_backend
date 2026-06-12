<div>
    @auth('customer')
        @if($inCart)
            <div class="d-flex align-items-center gap-1">
                <button class="btn btn-sm btn-icon qb-btn-accent" wire:click.debounce.250ms="decrement" wire:loading.attr="disabled" style="border-radius: 8px; width: 32px; height: 32px;">
                    <i class="ki-duotone ki-minus fs-3"></i>
                </button>
                <span class="fw-bold fs-6 px-2">{{ $qty }}</span>
                <button class="btn btn-sm btn-icon qb-btn-primary" wire:click.debounce.250ms="increment" wire:loading.attr="disabled" style="border-radius: 8px; width: 32px; height: 32px;">
                    <i class="ki-duotone ki-plus fs-3"></i>
                </button>
            </div>
        @else
            <button class="btn btn-icon qb-btn-primary" wire:click.debounce.250ms="addToCart" wire:loading.attr="disabled" style="border-radius: 10px; width: 40px; height: 40px;">
                <i class="fs-2 ki-duotone ki-purchase text-white">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
            </button>
        @endif
    @else
        <a href="{{ route('customer.login') }}" class="btn btn-icon qb-btn-primary" style="border-radius: 10px; width: 40px; height: 40px;">
            <i class="fs-2 ki-duotone ki-purchase text-white">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
        </a>
    @endauth
</div>
