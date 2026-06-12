<x-mail::message>
# PO #: {{ $purchase->po }}
#### Supplier: {{ $purchase->supplier->name }}
#### Purchase Date: {{ $purchase->purchased }}
#### Terms: {{ $purchase->expected }}
#### Items: {{ $purchase->received }} of {{ $purchase->items }} received
#### Amount: {{ number_format($purchase->total) }}
This purchase order is due for today. Please pay or settle the amount. Thank you
<x-mail::button :url="$url">
    View Purchase
</x-mail::button>
Thanks, <br>
{{ config('app.name') }}
</x-mail::message>