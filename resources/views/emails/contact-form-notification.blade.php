<x-mail::message>
# New Contact Form Submission

You have received a new message from the RLCPS website contact form.

<x-mail::panel>
**From:** {{ $contact->name }}

**Email:** {{ $contact->email }}

**Subject:** {{ ucwords(str_replace('-', ' ', $contact->subject)) }}

**Submitted:** {{ $contact->created_at->format('F j, Y \a\t g:i A') }}
</x-mail::panel>

## Message

{{ $contact->message }}

---

<x-mail::table>
| Detail | Value |
|:-------|:------|
| IP Address | {{ $contact->ip_address ?? 'N/A' }} |
| User Agent | {{ Str::limit($contact->user_agent ?? 'N/A', 50) }} |
| Status | {{ ucfirst($contact->status) }} |
</x-mail::table>

<x-mail::button :url="'mailto:' . $contact->email . '?subject=Re: ' . urlencode('[RLCPS] ' . ucwords(str_replace('-', ' ', $contact->subject)))">
Reply to {{ $contact->name }}
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
