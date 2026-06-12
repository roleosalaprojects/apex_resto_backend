<div wire:poll.30s="refresh">
    <div class="card card-flush h-100">
        <div class="card-header pt-5">
            <h3 class="card-title align-items-start flex-column">
                <span class="card-label fw-bold text-dark">Top Products</span>
                <span class="text-gray-400 mt-1 fw-semibold fs-6">Best sellers today</span>
            </h3>
            <div class="card-toolbar">
                <button type="button" class="btn btn-sm btn-icon btn-light-primary" wire:click="refresh" wire:loading.attr="disabled">
                    <i class="ki-duotone ki-arrows-circle fs-2" wire:loading.class="spinner">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </button>
            </div>
        </div>
        <div class="card-body pt-5">
            @forelse($topProducts as $index => $product)
                <div class="d-flex align-items-center mb-5" wire:key="product-{{ $product->id }}">
                    <div class="me-4">
                        <span class="badge badge-circle badge-light-{{ $index === 0 ? 'warning' : ($index === 1 ? 'primary' : ($index === 2 ? 'info' : 'secondary')) }} fw-bold fs-6">
                            {{ $index + 1 }}
                        </span>
                    </div>
                    <div class="symbol symbol-45px me-4">
                        @if($product->image)
                            <img src="{{ asset($product->image) }}" alt="{{ $product->name }}" class="symbol-label">
                        @else
                            <span class="symbol-label bg-light-success">
                                <i class="ki-duotone ki-package fs-2 text-success">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                            </span>
                        @endif
                    </div>
                    <div class="flex-grow-1">
                        <a href="#" class="text-dark fw-bold text-hover-primary fs-6">
                            {{ Str::limit($product->name, 25) }}
                        </a>
                        <span class="text-muted fw-semibold d-block fs-7">
                            {{ number_format($product->total_qty) }} sold
                            <span class="bullet bullet-dot bg-gray-400 mx-1"></span>
                            {{ $product->transaction_count }} orders
                        </span>
                    </div>
                    <div class="text-end">
                        <span class="text-dark fw-bold fs-6">
                            {{ config('app.currency', '₱') }}{{ number_format($product->total_sales, 2) }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="text-center py-10">
                    <i class="ki-duotone ki-package fs-3x text-gray-300 mb-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <p class="text-gray-500 fs-6 mb-0">No sales data today</p>
                </div>
            @endforelse
        </div>
    </div>

    <style>
        .spinner {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</div>
