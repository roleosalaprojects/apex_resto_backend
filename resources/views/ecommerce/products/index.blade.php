<x-ecommerce.layout.app>
    @slot('styles')
        <style>
            .product-name {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        </style>
    @endslot
    <div class="container mb-5">
        <h1 class="fw-bolder fs-2x mb-2" style="color: #1a1a2e;">Our Products</h1>
        <p class="text-muted fs-5 mb-0">Browse our selection of quality items</p>
    </div>
    <livewire:ecommerce.product-page></livewire:ecommerce.product-page>
    @slot('scripts') @endslot
</x-ecommerce.layout.app>
