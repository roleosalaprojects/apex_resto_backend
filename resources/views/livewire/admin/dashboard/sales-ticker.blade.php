<div wire:poll.5s="checkForNewSales">
    <div class="card card-flush h-100">
        <div class="card-header pt-5">
            <h3 class="card-title align-items-start flex-column">
                <span class="card-label fw-bold text-dark">Live Sales</span>
                <span class="text-gray-400 mt-1 fw-semibold fs-6">Real-time transactions</span>
            </h3>
            <div class="card-toolbar">
                <span class="badge badge-light-success fs-7">
                    <span class="bullet bullet-dot bg-success me-2 animation-blink"></span>
                    Live
                </span>
            </div>
        </div>
        <div class="card-body pt-5">
            @forelse($recentSales as $index => $sale)
                <div class="d-flex align-items-center mb-5 {{ $index === 0 ? 'sale-item-new' : '' }}"
                     wire:key="sale-{{ $sale->id }}">
                    <div class="symbol symbol-40px me-4">
                        <span class="symbol-label bg-light-primary">
                            <i class="ki-duotone ki-basket fs-2 text-primary">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <a href="#" class="text-dark fw-bold text-hover-primary fs-6">
                            Sale #{{ $sale->id }}
                        </a>
                        <span class="text-muted fw-semibold d-block fs-7">
                            {{ $sale->sold_by?->name ?? 'System' }}
                            @if($sale->store)
                                <span class="bullet bullet-dot bg-gray-400 mx-1"></span>
                                {{ $sale->store->name }}
                            @endif
                        </span>
                    </div>
                    <div class="text-end">
                        <span class="text-dark fw-bold fs-6">
                            {{ config('app.currency', '₱') }}{{ number_format($sale->total, 2) }}
                        </span>
                        <span class="text-muted fw-semibold d-block fs-7">
                            {{ $sale->created_at->diffForHumans() }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="text-center py-10">
                    <i class="ki-duotone ki-basket fs-3x text-gray-300 mb-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                    </i>
                    <p class="text-gray-500 fs-6 mb-0">No sales today yet</p>
                </div>
            @endforelse
        </div>
    </div>

    <style>
        .animation-blink {
            animation: blink 1s ease-in-out infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        .sale-item-new {
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</div>
