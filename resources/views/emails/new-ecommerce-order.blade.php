<x-mail::message>
# New Ecommerce Order

A new order just came in through the shop.

**Reference:** {{ $order->reference }}
**Customer:** {{ $order->customer?->name ?? 'Unknown' }} ({{ $order->customer?->phone ?? '—' }})
**Total:** ₱{{ number_format($order->total, 2) }} · {{ $order->qty }} item(s)
**Placed:** {{ $order->created_at->format('M d, Y g:i A') }}

@if($order->payment_intent)
**Customer's payment intent:** {{ \Illuminate\Support\Str::headline($order->payment_intent) }}
@endif

<x-mail::table>
| Item                                             | Qty | Price       | Subtotal      |
|:-------------------------------------------------|:---:|:-----------:|:-------------:|
@foreach($order->lines as $line)
| {{ $line->item_name ?? $line->item?->name ?? 'Item #'.$line->item_id }} | {{ $line->qty }} | ₱{{ number_format($line->price, 2) }} | ₱{{ number_format($line->sub_total, 2) }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$adminUrl">
Open in Admin
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
