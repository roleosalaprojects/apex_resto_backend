<x-mail::message>
# Daily Sales Report - {{ $date }}

## Summary

<x-mail::table>
| Metric | Today | Previous Day | Change |
|:-------|------:|-------------:|-------:|
| Sales | ₱{{ number_format($data['summary']['sales'], 2) }} | ₱{{ number_format($data['summary']['comparison']['previous_period']['sales'], 2) }} | {{ $data['summary']['comparison']['change_pct'] !== null ? $data['summary']['comparison']['change_pct'] . '%' : 'N/A' }} |
| Refunds | ₱{{ number_format($data['summary']['refunds'], 2) }} | ₱{{ number_format($data['summary']['comparison']['previous_period']['refunds'], 2) }} | - |
| Profit | ₱{{ number_format($data['summary']['profit'], 2) }} | ₱{{ number_format($data['summary']['comparison']['previous_period']['profit'], 2) }} | - |
| Transactions | {{ $data['summary']['transactions'] }} | {{ $data['summary']['comparison']['previous_period']['transactions'] }} | - |
</x-mail::table>

@if(!empty($data['summary']['top_items']))
## Top Items

<x-mail::table>
| Item | Qty Sold | Sales |
|:-----|--------:|------:|
@foreach($data['summary']['top_items'] as $item)
| {{ $item['item_name'] }} | {{ $item['qty_sold'] }} | ₱{{ number_format($item['total_sales'], 2) }} |
@endforeach
</x-mail::table>
@endif

@if(!empty($data['margin_alerts']))
## Margin Alerts

<x-mail::table>
| Item | Old Margin | New Margin | Drop |
|:-----|----------:|-----------:|-----:|
@foreach($data['margin_alerts'] as $alert)
| {{ $alert['item_name'] }} | {{ number_format($alert['old_margin'], 2) }}% | {{ number_format($alert['new_margin'], 2) }}% | -{{ number_format($alert['margin_drop_pct'], 2) }}% |
@endforeach
</x-mail::table>
@endif

@if(!empty($data['cashless_breakdown']))
## Cashless Sales (Web Admin)

These came in via the web admin recording payments against ecommerce orders — they are part of the Sales total above but broken out here by method.

<x-mail::table>
| Method | Count | Total |
|:-------|------:|------:|
@foreach($data['cashless_breakdown'] as $row)
| {{ $row['label'] }} | {{ $row['count'] }} | ₱{{ number_format($row['total'], 2) }} |
@endforeach
</x-mail::table>
@endif

@if(!empty($data['pending_cheques']) && $data['pending_cheques']['count'] > 0)
## Pending Cheques

{{ $data['pending_cheques']['count'] }} cheque(s) still awaiting clearing — ₱{{ number_format($data['pending_cheques']['total'], 2) }} not yet in the bank.
@if(!empty($data['pending_cheques']['oldest_days']))
Oldest is **{{ $data['pending_cheques']['oldest_days'] }} day(s) old**.
@endif

<x-mail::button :url="config('app.url') . '/admin/pending-cheques'">
Review pending cheques
</x-mail::button>
@endif

Thanks,<br>
Apex
</x-mail::message>
