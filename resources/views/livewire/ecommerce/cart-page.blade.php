<div>
    <style>
        .qb-pay-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px;
        }
        .qb-pay-option {
            display: flex; align-items: center;
            padding: 14px 16px;
            border: 2px solid #e4e6ef;
            border-radius: 10px;
            cursor: pointer; user-select: none;
            background: #fff;
            transition: border-color 120ms, background 120ms, transform 80ms;
        }
        .qb-pay-option:hover { border-color: rgba(var(--qb-primary-rgb), 0.45); }
        .qb-pay-option:active { transform: scale(0.99); }
        .qb-pay-option.is-active {
            border-color: var(--qb-primary);
            background: rgba(var(--qb-primary-rgb), 0.06);
        }
        .qb-pay-option.is-active i { color: var(--qb-primary); }
    </style>

    <div class="container">
        <h1 class="fs-2x fw-bold mb-8" style="color: #1a1a2e;">My Cart</h1>

        @if(session('error'))
            <div class="alert alert-danger mb-5">{{ session('error') }}</div>
        @endif

        @if($cartItems->isEmpty())
            <div class="card qb-card">
                <div class="card-body text-center py-15">
                    <i class="ki-duotone ki-handcart fs-3x text-gray-400 mb-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <p class="fs-4 text-gray-600 mb-5">Your cart is empty.</p>
                    <a href="{{ route('shops.products.index') }}" class="btn qb-btn-primary">Browse Products</a>
                </div>
            </div>
        @else
            <div class="card qb-card mb-5">
                <div class="card-body">
                    <table class="table table-row-bordered table-row-gray-200 align-middle gy-4">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th>Product</th>
                                <th class="text-center" style="width: 160px;">Qty</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-end" style="width: 60px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cartItems as $cartItem)
                                <tr wire:key="cart-item-{{ $cartItem->id }}">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-50px me-3">
                                                <x-ecommerce.product-image :item="$cartItem->item" class="symbol-label" emoji-class="fs-2" style="border-radius: 8px; object-fit: cover;" />
                                            </div>
                                            <div>
                                                <span class="fw-bold">{{ $cartItem->item->name }}</span>
                                                <br><span class="text-muted fs-7">{{ $cartItem->item->barcode }}</span>
                                                @php $stock = (int) ($stockMap[$cartItem->item_id] ?? 0); @endphp
                                                <br><span class="badge badge-sm {{ $stock > 0 ? 'badge-light-success' : 'badge-light-danger' }}" style="border-radius: 20px;">{{ $stock }} in stock</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                            <button class="btn btn-sm btn-icon qb-btn-accent" style="border-radius: 8px;" wire:click="updateQty({{ $cartItem->id }}, {{ $cartItem->qty - 1 }})">
                                                <i class="ki-duotone ki-minus text-white fs-4"></i>
                                            </button>
                                            <input type="number" class="form-control form-control-sm text-center fw-bold" style="width: 70px; border-radius: 8px;" value="{{ $cartItem->qty }}" min="0" wire:change="updateQty({{ $cartItem->id }}, $event.target.value)">
                                            <button class="btn btn-sm btn-icon qb-btn-primary" style="border-radius: 8px;" wire:click="updateQty({{ $cartItem->id }}, {{ $cartItem->qty + 1 }})">
                                                <i class="ki-duotone ki-plus text-white fs-4"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="text-end fw-semibold">
                                        ₱{{ number_format($cartItem->price, 2) }}
                                        @if(isset($tierInfo[$cartItem->item_id]))
                                            <br><span class="text-muted fs-8">Tier pricing</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold qb-price">₱{{ number_format($cartItem->qty * $cartItem->price, 2) }}</td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-icon btn-light-danger" style="border-radius: 8px;" wire:click="removeItem({{ $cartItem->id }})" wire:confirm="Remove this item from your cart?">
                                            <i class="ki-duotone ki-trash fs-4 text-white">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                                <span class="path4"></span>
                                                <span class="path5"></span>
                                            </i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end fw-bold fs-4">Total:</td>
                                <td class="text-end fw-bolder fs-3 qb-price">₱{{ number_format($total, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card qb-card mb-5">
                <div class="card-body">
                    <label class="form-label fw-bold mb-3">How will you pay?</label>
                    <div class="qb-pay-grid">
                        @php
                            $intents = [
                                ['value' => \App\Models\Ecommerce\EcommerceOrder::PAYMENT_INTENT_CASH_ON_PICKUP, 'label' => 'Cash on Pickup', 'sub' => 'Pay at the store', 'icon' => 'ki-cash'],
                                ['value' => \App\Models\Ecommerce\EcommerceOrder::PAYMENT_INTENT_GCASH, 'label' => 'GCash / E-Wallet', 'sub' => 'Send transfer reference', 'icon' => 'ki-wallet'],
                                ['value' => \App\Models\Ecommerce\EcommerceOrder::PAYMENT_INTENT_BANK_TRANSFER, 'label' => 'Bank Transfer', 'sub' => 'Direct deposit', 'icon' => 'ki-bank'],
                                ['value' => \App\Models\Ecommerce\EcommerceOrder::PAYMENT_INTENT_CHEQUE, 'label' => 'Cheque', 'sub' => 'Hand cheque at pickup', 'icon' => 'ki-bill'],
                            ];
                        @endphp
                        @foreach($intents as $option)
                            <label class="qb-pay-option {{ $paymentIntent === $option['value'] ? 'is-active' : '' }}">
                                <input type="radio"
                                       name="payment_intent_radio"
                                       value="{{ $option['value'] }}"
                                       wire:model.live="paymentIntent"
                                       hidden>
                                <i class="ki-duotone {{ $option['icon'] }} fs-2x me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                <div class="d-flex flex-column">
                                    <span class="fw-bold">{{ $option['label'] }}</span>
                                    <span class="text-muted fs-7">{{ $option['sub'] }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <div class="text-muted fs-7 mt-2">
                        You're not paying now — this just helps the store know what to expect when you pick up.
                    </div>
                </div>
            </div>

            <div class="card qb-card mb-5">
                <div class="card-body">
                    <label class="form-label fw-bold">Order Note (optional)</label>
                    <textarea class="form-control" rows="2" wire:model="note" placeholder="Add any special instructions..." style="border-radius: 8px;"></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-between mb-10 pb-5">
                <a href="{{ route('shops.products.index') }}" class="btn btn-light" style="border-radius: 8px;">Continue Shopping</a>
                <button class="btn qb-btn-primary" id="btnPlaceOrder" wire:loading.attr="disabled" style="border-radius: 8px; padding: 10px 30px;">
                    <span wire:loading.remove wire:target="placeOrder">Place Order</span>
                    <span wire:loading wire:target="placeOrder">
                        <span class="spinner-border spinner-border-sm me-1"></span> Processing...
                    </span>
                </button>
            </div>

            <script>
                document.getElementById('btnPlaceOrder').addEventListener('click', function () {
                    Swal.fire({
                        title: 'Place Order',
                        html: 'Are you sure you want to place this order?<br><strong>Total: ₱{{ number_format($total, 2) }}</strong>',
                        icon: 'question',
                        showCancelButton: true,
                        buttonsStyling: false,
                        confirmButtonText: 'Yes, place order!',
                        cancelButtonText: 'No, go back',
                        customClass: {
                            confirmButton: 'btn qb-btn-primary me-2',
                            cancelButton: 'btn btn-active-light'
                        }
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            @this.call('placeOrder');
                        }
                    });
                });
            </script>
        @endif
    </div>
</div>
