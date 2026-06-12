<div>
    <form wire:submit.debounce.200ms="search">
        <div class="container">
            <div wire:loading.delay wire:target="reinitialize">
                <div class="page-loader">
                    <span class="spinner-border" role="status" style="color: var(--qb-primary);">
                        <span class="visually-hidden">Loading...</span>
                    </span>
                </div>
            </div>

            <!--begin::Search Bar-->
            <div class="d-flex justify-content-center mb-6">
                <div class="d-flex w-100" style="max-width: 600px;">
                    <input type="text" class="form-control" name="search" placeholder="Search products..." wire:model="query" style="border-radius: 10px 0 0 10px; border: 2px solid #e0e0e0; border-right: none;">
                    <button type="submit" class="btn btn-icon qb-btn-primary" style="border-radius: 0 10px 10px 0; min-width: 50px;">
                        <i class="ki-duotone ki-magnifier fs-2 text-white">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                </div>
            </div>
            <!--end::Search Bar-->

            <!--begin::Category Filter Display-->
            @if($category && $categoryName)
            <div class="d-flex justify-content-center mb-6">
                <div class="d-flex align-items-center gap-2 px-4 py-2" style="background: rgba(var(--qb-primary-rgb, 255, 140, 105), 0.1); border-radius: 20px;">
                    <span class="fw-semibold" style="color: #1a1a2e;">Category: {{ ucwords(strtolower($categoryName)) }}</span>
                    <button type="button" wire:click="clearCategory" class="btn btn-icon btn-sm" style="width: 24px; height: 24px; background: rgba(var(--qb-primary-rgb, 255, 140, 105), 0.2); border-radius: 50%;">
                        <i class="ki-duotone ki-cross fs-6" style="color: var(--qb-primary);"><span class="path1"></span><span class="path2"></span></i>
                    </button>
                </div>
            </div>
            @endif
            <!--end::Category Filter Display-->

            <div wire:loading.remove>
                <div class="row">
                    @forelse($products as $product)
                        <div class="col-md-4 col-xl-3 mb-5">
                            <div class="card qb-card h-100 d-flex flex-column">
                                {{-- Link wraps image + name + barcode only — nested <button> inside
                                     <a> is invalid HTML and Chromium drops the click without
                                     navigating. Price + cart button live in their own row outside
                                     the link. --}}
                                <a href="{{ route('shops.products.show', $product->id) }}" class="text-decoration-none" style="color: inherit;">
                                    <div class="d-flex justify-content-center" style="background: #f8f9fa; border-radius: 12px 12px 0 0; padding: 20px 0;">
                                        <div class="symbol symbol-lg-200px symbol-md-150px symbol-sm-150px symbol-150px product-name">
                                            <x-ecommerce.product-image :item="$product" class="symbol-label" emoji-class="fs-4x" style="border-radius: 8px;" loading="lazy" />
                                        </div>
                                    </div>
                                    <div class="card-body pb-0">
                                        <span class="fs-4 fw-bold" style="color: #1a1a2e;">{{ $product->name }}</span>
                                        <br>
                                        <span class="fs-7 text-muted">{{ $product->barcode }}</span>
                                    </div>
                                </a>
                                <div class="card-body pt-2 mt-auto">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="fs-3 qb-price">
                                            @if($product->wholesalePriceTiers->isNotEmpty())
                                                <span class="badge badge-sm badge-primary mb-1">Volume Pricing</span><br>
                                                From ₱{{ number_format(max(0, $product->price - $product->wholesalePriceTiers->max('discount')), 2) }}
                                            @else
                                                ₱{{ number_format(round($product->price == 0 ? $product->cost + ($product->cost * ($product->markup / 100)) : $product->price, 2), 2) }}
                                            @endif
                                        </span>
                                        <livewire:ecommerce.add-to-cart-button :item-id="$product->id" :wire:key="'cart-btn-'.$product->id" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-15">
                            <div class="symbol symbol-2by3 symbol-200px mb-5">
                                <img src="{{ asset('assets/media/illustrations/sketchy-1/16.png') }}" alt="No products found.">
                            </div>
                            <p class="fs-2 fw-semibold text-gray-600">No products found.</p>
                        </div>
                    @endforelse
                </div>
                <div class="d-flex justify-content-center my-10">
                    {{ $products->links('livewire.components.pagination.pagination-links-view') }}
                </div>
            </div>
        </div>
    </form>
</div>
