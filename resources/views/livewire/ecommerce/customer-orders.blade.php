<div>
    <style>
        /* Page heading + section spacing */
        .qb-orders-title { font-size: 2rem; font-weight: 700; color: #0f172a; letter-spacing: -0.02em; }
        .qb-orders-subtitle { color: #64748b; font-size: 0.875rem; margin-top: 4px; }

        /* Filter bar */
        .qb-filter-bar {
            background: #fff;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }
        .qb-search-wrap { position: relative; }
        .qb-search-wrap .ki-magnifier {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; pointer-events: none; font-size: 18px;
        }
        .qb-search-input {
            padding: 11px 12px 11px 42px !important;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            font-size: 0.9rem;
            transition: border-color 120ms, box-shadow 120ms;
        }
        .qb-search-input:focus {
            border-color: var(--qb-primary);
            box-shadow: 0 0 0 4px rgba(var(--qb-primary-rgb), 0.12);
        }
        .qb-sort-select {
            padding: 11px 36px 11px 14px !important;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            font-size: 0.9rem;
            font-weight: 500;
            color: #334155;
            background-color: #fff;
            cursor: pointer;
        }

        /* Status pill row */
        .qb-pill-row { display: flex; flex-wrap: wrap; gap: 8px; }
        .qb-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
            transition: background 120ms, color 120ms, transform 100ms;
            border: 1px solid transparent;
        }
        .qb-pill:hover { background: #e2e8f0; }
        .qb-pill.is-active {
            background: var(--qb-primary);
            color: #fff;
            box-shadow: 0 2px 8px rgba(var(--qb-primary-rgb), 0.25);
        }
        .qb-pill-count {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 18px; height: 18px;
            padding: 0 6px;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.08);
            font-size: 11px; font-weight: 700;
        }
        .qb-pill.is-active .qb-pill-count { background: rgba(255, 255, 255, 0.25); }

        /* Result summary line */
        .qb-result-meta {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 14px; padding-top: 14px;
            border-top: 1px solid #f1f5f9;
            color: #64748b; font-size: 0.8125rem;
        }
        .qb-clear-link {
            color: var(--qb-primary); font-weight: 600; cursor: pointer; user-select: none;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .qb-clear-link:hover { text-decoration: underline; }

        /* Order card v2 — status accent + clean stack */
        .qb-order-card-v2 {
            display: flex;
            background: #fff;
            border-radius: 14px;
            margin-bottom: 14px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.04);
            text-decoration: none;
            color: inherit;
            overflow: hidden;
            transition: transform 140ms ease, box-shadow 140ms ease;
        }
        .qb-order-card-v2:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.07), 0 4px 10px rgba(0, 0, 0, 0.04);
            color: inherit;
        }
        .qb-status-stripe {
            flex: 0 0 6px;
            background: #cbd5e1;
        }
        .qb-status-stripe[data-variant="warning"]    { background: #f59e0b; }
        .qb-status-stripe[data-variant="primary"]    { background: #2563eb; }
        .qb-status-stripe[data-variant="info"]       { background: #0ea5e9; }
        .qb-status-stripe[data-variant="preparing"]  { background: #8b5cf6; }
        .qb-status-stripe[data-variant="success"]    { background: #10b981; }
        .qb-status-stripe[data-variant="danger"]     { background: #ef4444; }
        .qb-order-body { flex: 1; padding: 18px 22px; min-width: 0; }

        .qb-order-head {
            display: flex; justify-content: space-between; align-items: flex-start;
            gap: 12px; flex-wrap: wrap;
        }
        .qb-order-ref {
            font-size: 1.05rem; font-weight: 700; color: #0f172a;
            letter-spacing: 0.02em;
        }
        .qb-order-time {
            color: #64748b; font-size: 0.8125rem; margin-top: 2px;
        }
        .qb-order-time-rel { color: #334155; font-weight: 500; }

        .qb-status-chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px; font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }
        .qb-status-chip[data-variant="warning"]    { background: #fef3c7; color: #92400e; }
        .qb-status-chip[data-variant="primary"]    { background: #dbeafe; color: #1e40af; }
        .qb-status-chip[data-variant="info"]       { background: #cffafe; color: #155e75; }
        .qb-status-chip[data-variant="preparing"]  { background: #f3e8ff; color: #6b21a8; }
        .qb-status-chip[data-variant="success"]    { background: #d1fae5; color: #065f46; }
        .qb-status-chip[data-variant="danger"]     { background: #fee2e2; color: #991b1b; }

        .qb-status-chip::before {
            content: ''; display: inline-block;
            width: 6px; height: 6px; border-radius: 50%;
            background: currentColor;
        }

        .qb-order-items {
            margin-top: 14px;
            padding: 12px 14px;
            background: #f8fafc;
            border-radius: 10px;
            font-size: 0.875rem;
        }
        .qb-order-item-line {
            display: flex; justify-content: space-between; gap: 10px;
            color: #334155;
        }
        .qb-order-item-line + .qb-order-item-line { margin-top: 4px; }
        .qb-order-item-name {
            font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            min-width: 0; flex: 1;
        }
        .qb-order-item-qty { color: #64748b; flex-shrink: 0; }
        .qb-order-more { color: #94a3b8; font-size: 0.75rem; margin-top: 6px; font-weight: 500; }

        .qb-order-foot {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 16px; padding-top: 14px;
            border-top: 1px dashed #e2e8f0;
            gap: 12px;
        }
        .qb-order-total-block { display: flex; flex-direction: column; }
        .qb-order-total-label { color: #64748b; font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600; }
        .qb-order-total-value { font-size: 1.4rem; font-weight: 800; color: #0f172a; line-height: 1.1; }
        /* Filled pill so the action reads as "click me", not just an
           accent label. The entire card stays linkable; this is the
           visual affordance for customers who don't realise that. */
        .qb-order-cta {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--qb-primary); color: #fff;
            font-weight: 600; font-size: 0.8125rem;
            padding: 8px 14px; border-radius: 999px;
            transition: gap 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
        }
        .qb-order-card-v2:hover .qb-order-cta {
            gap: 10px;
            transform: translateX(2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }

        /* Empty states */
        .qb-empty {
            background: #fff; border-radius: 14px; padding: 60px 30px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }
        .qb-empty-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 64px; height: 64px;
            border-radius: 50%;
            background: rgba(var(--qb-primary-rgb), 0.08);
            color: var(--qb-primary);
            margin-bottom: 16px;
        }
        .qb-empty h3 { font-size: 1.125rem; color: #0f172a; margin: 0 0 6px; }
        .qb-empty p  { color: #64748b; margin: 0 0 18px; }
    </style>

    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-5">
        <div>
            <h1 class="qb-orders-title m-0">My Orders</h1>
            @if($totalCount > 0)
                <div class="qb-orders-subtitle">{{ $totalCount }} {{ \Illuminate\Support\Str::plural('order', $totalCount) }} total</div>
            @endif
        </div>
    </div>

    {{-- Flash is rendered once in customer/layouts/app.blade.php — removed
         from here to avoid the duplicate "placed successfully" banner. --}}

    @if($totalCount > 0)
        <div class="qb-filter-bar mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-md-7">
                    <div class="qb-search-wrap">
                        <i class="ki-duotone ki-magnifier"><span class="path1"></span><span class="path2"></span></i>
                        <input type="search"
                               class="form-control qb-search-input"
                               placeholder="Search by reference or note…"
                               wire:model.live.debounce.300ms="search"
                               autocomplete="off">
                    </div>
                </div>
                <div class="col-md-5">
                    <select class="form-select qb-sort-select" wire:model.live="sort">
                        <option value="newest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                        <option value="total_high">Highest total</option>
                        <option value="total_low">Lowest total</option>
                    </select>
                </div>
            </div>

            <div class="qb-pill-row mt-3">
                @foreach($this->statusOptions as $option)
                    <span class="qb-pill {{ $status === $option['slug'] ? 'is-active' : '' }}"
                          wire:click="$set('status', '{{ $option['slug'] }}')">
                        {{ $option['label'] }}
                    </span>
                @endforeach
            </div>

            <div class="qb-result-meta">
                <span wire:loading.remove wire:target="search,status,sort">
                    Showing <strong>{{ $orders->count() }}</strong> of <strong>{{ $orders->total() }}</strong>
                    @if($this->hasActiveFilters())
                        — filtered from {{ $totalCount }}
                    @endif
                </span>
                <span wire:loading wire:target="search,status,sort" class="text-muted">
                    <i class="ki-outline ki-loading fs-5 me-1 spin"></i> Searching…
                </span>
                @if($this->hasActiveFilters())
                    <span class="qb-clear-link" wire:click="clearFilters">
                        <i class="ki-outline ki-cross-circle fs-5"></i> Clear filters
                    </span>
                @endif
            </div>
        </div>
    @endif

    @if($orders->isEmpty())
        <div class="qb-empty">
            @if($totalCount === 0)
                <div class="qb-empty-icon">
                    <i class="ki-duotone ki-handcart fs-2x"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                </div>
                <h3>No orders yet</h3>
                <p>Start shopping and your orders will show up here.</p>
                <a href="{{ route('shops.products.index') }}" class="btn qb-btn-primary">Browse Products</a>
            @else
                <div class="qb-empty-icon">
                    <i class="ki-duotone ki-magnifier fs-2x"><span class="path1"></span><span class="path2"></span></i>
                </div>
                <h3>No matches</h3>
                <p>Try a different search term or status.</p>
                <button type="button" class="btn qb-btn-primary" wire:click="clearFilters">Clear filters</button>
            @endif
        </div>
    @else
        @foreach($orders as $order)
            @php
                $variant = $order->statusBadgeVariant();
                $lineCount = $order->lines->count();
                $visibleLines = $order->lines->take(2);
                $hiddenCount = max(0, $lineCount - 2);
            @endphp
            <a href="{{ route('customer.orders.show', $order) }}"
               class="qb-order-card-v2"
               wire:key="order-{{ $order->id }}">
                <span class="qb-status-stripe" data-variant="{{ $variant }}"></span>
                <div class="qb-order-body">
                    <div class="qb-order-head">
                        <div>
                            <div class="qb-order-ref">{{ $order->reference }}</div>
                            <div class="qb-order-time">
                                <span class="qb-order-time-rel">{{ $order->created_at->diffForHumans() }}</span>
                                · {{ $order->created_at->format('M d, Y h:i A') }}
                            </div>
                        </div>
                        <span class="qb-status-chip" data-variant="{{ $variant }}">
                            {{ $order->statusLabel() }}
                        </span>
                    </div>

                    <div class="qb-order-items">
                        @foreach($visibleLines as $line)
                            <div class="qb-order-item-line">
                                <span class="qb-order-item-name">{{ $line->item_name }}</span>
                                <span class="qb-order-item-qty">× {{ rtrim(rtrim(number_format($line->qty, 2), '0'), '.') }}</span>
                            </div>
                        @endforeach
                        @if($hiddenCount > 0)
                            <div class="qb-order-more">+ {{ $hiddenCount }} more {{ \Illuminate\Support\Str::plural('item', $hiddenCount) }}</div>
                        @endif
                    </div>

                    <div class="qb-order-foot">
                        <div class="qb-order-total-block">
                            <span class="qb-order-total-label">Total · {{ $order->qty }} {{ \Illuminate\Support\Str::plural('item', $order->qty) }}</span>
                            <span class="qb-order-total-value">₱{{ number_format($order->total, 2) }}</span>
                        </div>
                        <span class="qb-order-cta">
                            <i class="ki-duotone ki-eye fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            View Order
                        </span>
                    </div>
                </div>
            </a>
        @endforeach

        <div class="d-flex justify-content-center mt-4">
            {{ $orders->links() }}
        </div>
    @endif
</div>
