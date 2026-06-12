<?php

namespace App\Livewire\Ecommerce;

use App\Models\Ecommerce\Cart;
use App\Models\Ecommerce\CartItem;
use App\Models\Products\ItemStore;
use App\Services\WholesalePricingService;
use Livewire\Component;

class AddToCartButton extends Component
{
    public int $itemId;

    public int $qty = 0;

    public bool $inCart = false;

    public function mount(int $itemId): void
    {
        $this->itemId = $itemId;
        $this->loadCartState();
    }

    public function addToCart(): void
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return;
        }

        $cart = Cart::firstOrCreate(['customer_id' => $customer->id]);

        $item = \App\Models\Products\Item::findOrFail($this->itemId);
        $pricingService = app(WholesalePricingService::class);
        $price = $pricingService->getPrice($item, $customer, 1);

        CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'item_id' => $this->itemId],
            ['qty' => 1, 'price' => $price]
        );

        $this->loadCartState();
        $this->dispatch('cart-updated');
    }

    public function increment(): void
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return;
        }

        $cartItem = CartItem::whereHas('cart', fn ($q) => $q->where('customer_id', $customer->id))
            ->where('item_id', $this->itemId)
            ->first();

        if ($cartItem) {
            $stock = ItemStore::where('item_id', $this->itemId)->sum('stock');
            if ($cartItem->qty < $stock) {
                $newQty = $cartItem->qty + 1;
                $cartItem->increment('qty');

                $item = $cartItem->item;
                $pricingService = app(WholesalePricingService::class);
                $newPrice = $pricingService->getPrice($item, $customer, $newQty);
                $cartItem->update(['price' => $newPrice]);
            }
        }

        $this->loadCartState();
        $this->dispatch('cart-updated');
    }

    public function decrement(): void
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return;
        }

        $cartItem = CartItem::whereHas('cart', fn ($q) => $q->where('customer_id', $customer->id))
            ->where('item_id', $this->itemId)
            ->first();

        if ($cartItem) {
            if ($cartItem->qty <= 1) {
                $cartItem->delete();
            } else {
                $newQty = $cartItem->qty - 1;
                $cartItem->decrement('qty');

                $item = $cartItem->item;
                $pricingService = app(WholesalePricingService::class);
                $newPrice = $pricingService->getPrice($item, $customer, $newQty);
                $cartItem->update(['price' => $newPrice]);
            }
        }

        $this->loadCartState();
        $this->dispatch('cart-updated');
    }

    private function loadCartState(): void
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            $this->qty = 0;
            $this->inCart = false;

            return;
        }

        $cart = Cart::where('customer_id', $customer->id)->first();
        if ($cart) {
            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('item_id', $this->itemId)
                ->first();

            if ($cartItem) {
                $this->qty = $cartItem->qty;
                $this->inCart = true;

                return;
            }
        }

        $this->qty = 0;
        $this->inCart = false;
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\View\View
    {
        return view('livewire.ecommerce.add-to-cart-button');
    }
}
