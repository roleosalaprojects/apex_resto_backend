<x-mail::message>

# These Purchase Orders needs actions.

<x-mail::table>
| PO#    | Supplier    | Received    | Due Date | Amount    |
| ------ |:-----------:|:-----------:|----------|----------:|
@foreach($purchases as $purchase)
| {{ $purchase->po }} | {{ $purchase->supplier->name }} | {{ $purchase->received }} of {{ $purchase->items }} | {{ \Carbon\Carbon::parse($purchase->purchased)->addDays($purchase->expected -1)->format('M d, y') }} |{{ number_format($purchase->total,2) }} |
@endforeach
</x-mail::table>

Thanks, <br>
{{ config('app.name') }}
</x-mail::message>