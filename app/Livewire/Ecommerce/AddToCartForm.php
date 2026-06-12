<?php

namespace App\Livewire\Ecommerce;

use App\Models\Ecommerce\Cart;
use App\Models\Ecommerce\CartItem;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Services\WholesalePricingService;
use Livewire\Component;

/**
 * Add-to-cart UX for the /shop/products/{id} detail page.
 *
 * Distinct from AddToCartButton (used in the listing/grid tiles):
 * here we let the customer pick an exact quantity before adding,
 * so they can drop "500 of these" into their cart with one click
 * instead of tapping + four hundred and ninety-nine times.
 */
class AddToCartForm extends Component
{
    public int $itemId;

    /** Quantity the customer is about to add. */
    public int $qty = 1;

    /** Current qty already in cart for this item. */
    public int $cartQty = 0;

    public function mount(int $itemId): void
    {
        $this->itemId = $itemId;
        $this->loadCartState();
    }

    public function increment(): void
    {
        $stock = $this->stockOnHand();
        $room = max(0, $stock - $this->cartQty);
        if ($this->qty < $room) {
            $this->qty += 1;
        }
    }

    public function decrement(): void
    {
        if ($this->qty > 1) {
            $this->qty -= 1;
        }
    }

    public function updatedQty(): void
    {
        $this->qty = max(1, (int) $this->qty);
        $stock = $this->stockOnHand();
        $room = max(0, $stock - $this->cartQty);
        if ($room > 0 && $this->qty > $room) {
            $this->qty = $room;
        }
    }

    public function addToCart(): void
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return;
        }

        $stock = $this->stockOnHand();
        $room = max(0, $stock - $this->cartQty);
        $add = max(0, min($this->qty, $room));
        if ($add <= 0) {
            return;
        }

        $cart = Cart::firstOrCreate(['customer_id' => $customer->id]);
        $item = Item::findOrFail($this->itemId);
        $pricingService = app(WholesalePricingService::class);

        $existing = CartItem::where('cart_id', $cart->id)
            ->where('item_id', $this->itemId)
            ->first();

        $newQty = ($existing?->qty ?? 0) + $add;
        $newPrice = $pricingService->getPrice($item, $customer, $newQty);

        CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'item_id' => $this->itemId],
            ['qty' => $newQty, 'price' => $newPrice],
        );

        $this->loadCartState();
        $this->qty = 1;
        $this->dispatch('cart-updated');
    }

    private function stockOnHand(): int
    {
        return (int) ItemStore::where('item_id', $this->itemId)->sum('stock');
    }

    private function loadCartState(): void
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            $this->cartQty = 0;

            return;
        }

        $cart = Cart::where('customer_id', $customer->id)->first();
        if (! $cart) {
            $this->cartQty = 0;

            return;
        }

        $this->cartQty = (int) (CartItem::where('cart_id', $cart->id)
            ->where('item_id', $this->itemId)
            ->value('qty') ?? 0);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.ecommerce.add-to-cart-form', [
            'stock' => $this->stockOnHand(),
        ]);
    }
}
