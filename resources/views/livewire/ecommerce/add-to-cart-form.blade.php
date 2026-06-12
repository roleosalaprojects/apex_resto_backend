<div>
    @auth('customer')
        @php
            $room = max(0, $stock - $cartQty);
            $disabled = $room <= 0;
        @endphp

        @if ($cartQty > 0)
            <div class="text-muted fs-7 mb-2">
                <i class="ki-duotone ki-basket fs-5 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                {{ $cartQty }} already in your cart
            </div>
        @endif

        <div class="d-flex align-items-stretch gap-3 flex-wrap">
            {{-- Quantity stepper --}}
            <div class="d-flex align-items-center" style="border: 1px solid #e4e6ef; border-radius: 10px; overflow: hidden; background: #fff;">
                <button type="button"
                        class="btn btn-icon"
                        style="width: 44px; height: 48px; border-radius: 0;"
                        wire:click="decrement"
                        wire:loading.attr="disabled"
                        @disabled($disabled || $qty <= 1)>
                    <i class="ki-duotone ki-minus fs-3"></i>
                </button>
                <input type="number"
                       inputmode="numeric"
                       min="1"
                       max="{{ max(1, $room) }}"
                       class="form-control form-control-flush text-center fw-bold"
                       style="width: 80px; height: 48px; border: none; box-shadow: none; font-size: 18px;"
                       wire:model.lazy="qty"
                       @disabled($disabled)>
                <button type="button"
                        class="btn btn-icon"
                        style="width: 44px; height: 48px; border-radius: 0;"
                        wire:click="increment"
                        wire:loading.attr="disabled"
                        @disabled($disabled || $qty >= $room)>
                    <i class="ki-duotone ki-plus fs-3"></i>
                </button>
            </div>

            {{-- Big Add to Cart CTA --}}
            <button type="button"
                    class="btn fw-bold px-5 d-flex align-items-center gap-2"
                    style="background: var(--qb-primary); color: {{ $disabled ? '#fff' : 'var(--bs-white, #fff)' }}; border-radius: 10px; height: 48px; min-width: 180px; justify-content: center;"
                    wire:click="addToCart"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-75"
                    @disabled($disabled)>
                <i class="ki-duotone ki-purchase fs-2 text-white">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <span wire:loading.remove wire:target="addToCart">
                    {{ $disabled ? 'Out of Stock' : 'Add to Cart' }}
                </span>
                <span wire:loading wire:target="addToCart">Adding…</span>
            </button>
        </div>

        @if ($room > 0 && $room <= 10)
            <div class="text-muted fs-7 mt-2">
                Only {{ $room }} more available to add.
            </div>
        @endif
    @else
        <a href="{{ route('customer.login') }}"
           class="btn fw-bold px-5 d-flex align-items-center gap-2"
           style="background: var(--qb-primary); color: #fff; border-radius: 10px; height: 48px; min-width: 220px; justify-content: center;">
            <i class="ki-duotone ki-entrance-right fs-2 text-white"><span class="path1"></span><span class="path2"></span></i>
            Sign in to add to cart
        </a>
    @endauth
</div>
