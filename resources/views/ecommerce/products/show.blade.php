@php
    // Per-page OG values for the link preview when someone shares this
    // product on Messenger / iMessage / Twitter. Falls back to layout
    // defaults when a field is missing.
    //
    // BrandingService called directly: the view composer that injects
    // $branding only fires for the layout component, not the outer
    // page scope, so we resolve it here for the title prefix.
    $pageBrand = app(\App\Services\BrandingService::class)->forStorefront()['brand_name'] ?? 'Quick Baskets';
    $ogProductImage = $item->displayImageUrl();
    $ogProductDescription = $item->description
        ? \Illuminate\Support\Str::limit(strip_tags($item->description), 200)
        : ($item->price > 0
            ? '₱'.number_format((float) $item->price, 2).' — shop online or pick up in store.'
            : 'Now available — shop online or pick up in store.');
@endphp
<x-ecommerce.layout.app
    :og-title="$item->name.' — '.$pageBrand"
    :og-description="$ogProductDescription"
    :og-image="$ogProductImage"
    og-type="product"
>
    @slot('styles')
    <style>
        .qb-product-wrap { max-width: 1100px; margin: 0 auto; }
        .qb-product-card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .qb-product-image {
            background: #f8f9fa; display: flex; align-items: center;
            justify-content: center; padding: 2rem; min-height: 360px;
        }
        .qb-product-image img {
            max-width: 100%; max-height: 420px; object-fit: contain;
        }
        .qb-product-meta { color: #7e8299; font-size: 13px; }
        .qb-product-name { font-size: 1.85rem; font-weight: 700; color: #1a1a2e; }
        .qb-product-price-big { font-size: 2.25rem; font-weight: 800; color: var(--qb-accent); }
        .qb-stock-pill {
            display: inline-block; padding: 4px 12px; border-radius: 999px;
            font-size: 12px; font-weight: 600;
        }
        .qb-stock-in    { background: #e6fffa; color: #047857; }
        .qb-stock-low   { background: #fffbeb; color: #b45309; }
        .qb-stock-out   { background: #fee2e2; color: #b91c1c; }
        .qb-tier-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 14px; background: #f8fafc; border-radius: 10px;
            margin-bottom: 8px;
        }
        .qb-back-link {
            color: var(--qb-primary); font-weight: 600; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
        }
    </style>
    @endslot

    @php
        $totalStock = $item->stocks->sum('stock');
        $displayPrice = $item->price > 0
            ? $item->price
            : $item->cost + ($item->cost * (((float) $item->markup) / 100));
        $stockClass = $totalStock <= 0 ? 'qb-stock-out' : ($totalStock <= 10 ? 'qb-stock-low' : 'qb-stock-in');
        $stockLabel = $totalStock <= 0 ? 'Out of stock' : ($totalStock <= 10 ? "Only {$totalStock} left" : "In stock");
    @endphp

    <div class="container py-5">
        <div class="qb-product-wrap">

            <div class="mb-3">
                <a href="{{ route('shops.products.index') }}" class="qb-back-link">
                    <i class="ki-duotone ki-arrow-left fs-3"><span class="path1"></span><span class="path2"></span></i>
                    Back to Products
                </a>
            </div>

            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb fs-7 fw-semibold">
                    <li class="breadcrumb-item"><a href="{{ route('shops.') }}" style="color: var(--qb-primary);">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('shops.products.index') }}" style="color: var(--qb-primary);">Products</a></li>
                    @if ($item->category)
                        <li class="breadcrumb-item">
                            <a href="{{ route('shops.products.index', ['category' => $item->category->id]) }}" style="color: var(--qb-primary);">
                                {{ ucwords(strtolower($item->category->name)) }}
                            </a>
                        </li>
                    @endif
                    <li class="breadcrumb-item active" aria-current="page">{{ $item->name }}</li>
                </ol>
            </nav>

            <div class="qb-product-card">
                <div class="row g-0">
                    <div class="col-lg-6">
                        <div class="qb-product-image">
                            <x-ecommerce.product-image :item="$item" emoji-class="display-1" />
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="p-5 p-lg-8">
                            @if ($item->category)
                                <div class="qb-product-meta mb-1">
                                    {{ ucwords(strtolower($item->category->name)) }}
                                </div>
                            @endif
                            <h1 class="qb-product-name mb-1">{{ $item->name }}</h1>
                            @if ($item->barcode)
                                <div class="qb-product-meta mb-4">Barcode: {{ $item->barcode }}</div>
                            @endif

                            <div class="d-flex align-items-center gap-3 mb-4">
                                <span class="qb-product-price-big">₱{{ number_format($displayPrice, 2) }}</span>
                                <span class="qb-stock-pill {{ $stockClass }}">{{ $stockLabel }}</span>
                            </div>

                            @if ($item->wholesalePriceTiers->isNotEmpty())
                                <div class="mb-4">
                                    <div class="fw-bold text-gray-800 mb-2">Volume Pricing</div>
                                    @foreach ($item->wholesalePriceTiers as $tier)
                                        @php
                                            $tierPrice = max(0, $displayPrice - (float) $tier->discount);
                                        @endphp
                                        <div class="qb-tier-row">
                                            <span class="text-gray-700">Buy {{ $tier->min_qty }}+ at</span>
                                            <span class="fw-bold" style="color: var(--qb-accent);">₱{{ number_format($tierPrice, 2) }} <span class="text-muted fs-7 fw-normal">/ each</span></span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($item->description)
                                <div class="mb-4">
                                    <div class="fw-bold text-gray-800 mb-1">Description</div>
                                    <div class="text-gray-700" style="white-space: pre-line;">{{ $item->description }}</div>
                                </div>
                            @endif

                            <div class="mt-4">
                                <livewire:ecommerce.add-to-cart-form :item-id="$item->id" :wire:key="'cart-form-'.$item->id" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @slot('scripts')@endslot
</x-ecommerce.layout.app>
