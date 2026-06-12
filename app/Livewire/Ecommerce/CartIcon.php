<?php

namespace App\Livewire\Ecommerce;

use App\Models\Ecommerce\Cart;
use Livewire\Attributes\On;
use Livewire\Component;

class CartIcon extends Component
{
    public int $count = 0;

    public function mount(): void
    {
        $this->loadCount();
    }

    #[On('cart-updated')]
    public function loadCount(): void
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            $this->count = 0;

            return;
        }

        $cart = Cart::where('customer_id', $customer->id)->first();
        $this->count = $cart ? $cart->items()->sum('qty') : 0;
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\View\View
    {
        return view('livewire.ecommerce.cart-icon');
    }
}
