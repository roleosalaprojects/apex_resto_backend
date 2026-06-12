<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $brandName ?? 'Quick Baskets' }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
# Hello!

Please click the button below to verify your email address.

<x-mail::button :url="$url">
Verify Email Address
</x-mail::button>

If you did not create an account, no further action is required.

Thanks,<br>
{{ $brandName ?? 'Quick Baskets' }}

{{-- Subcopy --}}
<x-slot:subcopy>
<x-mail::subcopy>
If you're having trouble clicking the "Verify Email Address" button, copy and paste the URL below into your web browser:
[{{ $url }}]({{ $url }})
</x-mail::subcopy>
</x-slot:subcopy>

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $brandName ?? 'Quick Baskets' }}. All rights reserved.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
