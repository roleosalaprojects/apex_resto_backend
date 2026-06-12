<x-mail::message>
# Weekly Sales Report - {{ $weekRange }}

## Summary

<x-mail::table>
| Metric | This Week | Previous Week | Change |
|:-------|----------:|--------------:|-------:|
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

@if(!empty($data['peak_hours_summary']))
## Peak Hours (Avg)

<x-mail::table>
| Day | Hour | Avg Sales | Avg Receipts |
|:----|-----:|----------:|-------------:|
@foreach($data['peak_hours_summary'] as $peak)
| {{ $peak['day_name'] }} | {{ $peak['hour'] }}:00 | ₱{{ number_format($peak['avg_sales'], 2) }} | {{ number_format($peak['avg_transactions'], 1) }} |
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

Thanks,<br>
Apex
</x-mail::message>
