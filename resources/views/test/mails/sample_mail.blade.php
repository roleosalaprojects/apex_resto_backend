<x-mail::message>
# Order Shipped
### Order #: {{ $payload }}
{{ $payload }}

<x-mail::button :url="$url" color="success">
View Order
</x-mail::button>
<x-mail::table>
| Laravel       | Table         | Example  |
| ------------- |:-------------:| --------:|
| Col 2 is      | Centered      | $10      |
| Col 3 is      | Right-Aligned | $20      |
</x-mail::table>

Thanks, <br>
{{ config('app.name') }}
</x-mail::message>