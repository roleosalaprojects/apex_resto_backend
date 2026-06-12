<x-ecommerce.layout.app>
    @slot('styles')
    @endslot
    <div class="container mb-5">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb fs-6 fw-semibold">
                <li class="breadcrumb-item"><a href="{{ route('shops.') }}" style="color: var(--qb-primary);">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Cart</li>
            </ol>
        </nav>
    </div>
    <livewire:ecommerce.cart-page />
    @slot('scripts')
    @endslot
</x-ecommerce.layout.app>
