<?php

namespace App\Livewire\Ecommerce;

use App\Models\Ecommerce\Cart;
use App\Models\Ecommerce\CartItem;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Ecommerce\EcommerceOrderLine;
use App\Models\Products\ItemStore;
use App\Models\User;
use App\Services\FcmService;
use App\Services\WholesalePricingService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CartPage extends Component
{
    public string $note = '';

    /**
     * Customer-signalled payment intent. NOT money received — just
     * "how do you intend to pay?". Defaults to cash_on_pickup because
     * that's the most common case for a brick-and-mortar pickup model.
     */
    public string $paymentIntent = EcommerceOrder::PAYMENT_INTENT_CASH_ON_PICKUP;

    public function updateQty(int $cartItemId, int $qty): void
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return;
        }

        $cartItem = CartItem::whereHas('cart', fn ($q) => $q->where('customer_id', $customer->id))
            ->findOrFail($cartItemId);

        $pricingService = app(WholesalePricingService::class);

        if ($qty < 1) {
            $cartItem->delete();
        } else {
            $stock = ItemStore::where('item_id', $cartItem->item_id)->sum('stock');
            $newQty = min($qty, (int) $stock);
            $item = $cartItem->item;
            $newPrice = $pricingService->getPrice($item, $customer, $newQty);
            $cartItem->update(['qty' => $newQty, 'price' => $newPrice]);
        }

        $this->dispatch('cart-updated');
    }

    public function removeItem(int $cartItemId): void
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return;
        }

        CartItem::whereHas('cart', fn ($q) => $q->where('customer_id', $customer->id))
            ->findOrFail($cartItemId)
            ->delete();

        $this->dispatch('cart-updated');
    }

    public function placeOrder(): mixed
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return null;
        }

        $cart = Cart::where('customer_id', $customer->id)->with('items.item')->first();
        if (! $cart || $cart->items->isEmpty()) {
            session()->flash('error', 'Your cart is empty.');

            return null;
        }

        // Validate stock for each item
        foreach ($cart->items as $cartItem) {
            $stock = ItemStore::where('item_id', $cartItem->item_id)->sum('stock');
            if ($stock <= 0) {
                session()->flash('error', "Item '{$cartItem->item->name}' is out of stock.");

                return null;
            }
            if ($cartItem->qty > $stock) {
                session()->flash('error', "Insufficient stock for '{$cartItem->item->name}'. Only {$stock} available.");

                return null;
            }
        }

        return DB::transaction(function () use ($cart, $customer) {
            $totalQty = $cart->items->sum('qty');
            $totalAmount = $cart->items->sum(fn ($ci) => $ci->qty * $ci->price);

            $order = EcommerceOrder::create([
                'reference' => EcommerceOrder::generateReference(),
                'customer_id' => $customer->id,
                'total' => $totalAmount,
                'qty' => $totalQty,
                'status' => 0,
                'note' => $this->note ?: null,
                'payment_intent' => in_array($this->paymentIntent, [
                    EcommerceOrder::PAYMENT_INTENT_CASH_ON_PICKUP,
                    EcommerceOrder::PAYMENT_INTENT_GCASH,
                    EcommerceOrder::PAYMENT_INTENT_BANK_TRANSFER,
                    EcommerceOrder::PAYMENT_INTENT_CHEQUE,
                ], true) ? $this->paymentIntent : null,
            ]);

            // First entry in the order's status history — null from,
            // null actor (customer-initiated through /shop checkout).
            $order->logStatusChange(null, EcommerceOrder::STATUS_PENDING, null, 'Placed via /shop');

            foreach ($cart->items as $cartItem) {
                EcommerceOrderLine::create([
                    'ecommerce_order_id' => $order->id,
                    'item_id' => $cartItem->item_id,
                    'item_name' => $cartItem->item->name,
                    'qty' => $cartItem->qty,
                    'price' => $cartItem->price,
                    'sub_total' => round($cartItem->qty * $cartItem->price, 2),
                ]);
            }

            // Clear cart
            $cart->items()->delete();

            // Notify staff with sales access (admin desktops + apex_dashboard
            // mobile clients). Scoped to role.sls so warehouse-only or
            // cashier-only employees don't get noise. Wrapped in try so an
            // FCM hiccup never kills checkout.
            //
            // Both channels — FCM push AND email — target the same
            // sls-flagged user set. FCM for people sitting at the
            // desk; email is the durable fallback for whoever isn't.
            $salesUsers = User::whereHas('role', fn ($q) => $q->where('sls', true))
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->get(['id', 'email', 'name']);

            try {
                if ($salesUsers->isNotEmpty()) {
                    (new FcmService)->sendToUsers(
                        $salesUsers->pluck('id')->toArray(),
                        'New Ecommerce Order',
                        "Order {$order->reference} — ₱".number_format($totalAmount, 2)." ({$totalQty} items)",
                        ['type' => 'ecommerce_order', 'id' => (string) $order->id],
                    );
                }
            } catch (\Exception $e) {
                \Log::warning('FCM notification failed for new order: '.$e->getMessage());
            }

            // Email fan-out. Queued (NewEcommerceOrder implements
            // ShouldQueue) so SMTP latency never blocks checkout — a
            // worker drains the email queue out-of-band. Each
            // recipient gets their own queued job so one bad address
            // can't poison the others.
            try {
                foreach ($salesUsers as $recipient) {
                    \Illuminate\Support\Facades\Mail::to($recipient->email)
                        ->send(new \App\Mail\NewEcommerceOrder($order));
                }
            } catch (\Exception $e) {
                \Log::warning('Email notification failed for new order: '.$e->getMessage());
            }

            session()->flash('success', "Order {$order->reference} placed successfully!");
            $this->dispatch('cart-updated');

            return $this->redirect(route('customer.orders'), navigate: false);
        });
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\View\View
    {
        $customer = auth('customer')->user();
        $cart = null;
        $cartItems = collect();
        $total = 0;

        $stockMap = collect();
        $tierInfo = collect();

        if ($customer) {
            $cart = Cart::where('customer_id', $customer->id)->with('items.item.category')->first();
            if ($cart) {
                $cartItems = $cart->items;
                $total = $cartItems->sum(fn ($ci) => $ci->qty * $ci->price);

                $itemIds = $cartItems->pluck('item_id');
                $stockMap = ItemStore::whereIn('item_id', $itemIds)
                    ->selectRaw('item_id, SUM(stock) as total_stock')
                    ->groupBy('item_id')
                    ->pluck('total_stock', 'item_id');

                $pricingService = app(WholesalePricingService::class);
                foreach ($cartItems as $cartItem) {
                    $tiers = $pricingService->getTiers($cartItem->item_id);
                    if ($tiers->isNotEmpty()) {
                        $tierInfo[$cartItem->item_id] = $tiers;
                    }
                }
            }
        }

        return view('livewire.ecommerce.cart-page', [
            'cartItems' => $cartItems,
            'total' => $total,
            'stockMap' => $stockMap,
            'tierInfo' => $tierInfo,
        ]);
    }
}
