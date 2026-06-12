<?php

namespace App\Services\Bi;

use App\Models\Bi\DailyCustomerMetric;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Ecommerce\ShopVisit;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * RFM segmentation + customer lifetime value, fed entirely by the
 * pre-aggregated daily_customer_metrics table (rebuilt nightly by
 * `bi:aggregate-daily`), plus the ecommerce funnel built from
 * shop_visits and ecommerce_orders.
 *
 * RFM scores are quintiles (1-5) computed by percent rank within the
 * analyzed tenant, so segments stay meaningful for both 50-customer
 * and 5,000-customer shops. Recency is days since the last purchase
 * (refund-only days don't count); monetary is net spend
 * (spend - refunds) over the window.
 *
 * Lifetime value here is HISTORICAL recorded profit per customer
 * (all-time, not windowed) — no projection model, so the number is
 * always defensible against the raw books.
 */
class CustomerIntelligenceService
{
    public const SEGMENTS = [
        'Champions' => 'Bought recently, buy often, spend the most — protect and reward them.',
        'Loyal' => 'Steady repeat buyers. Upsell and ask for referrals.',
        'Potential Loyalist' => 'Recent customers with a few purchases — nudge them into a habit.',
        'New' => 'First purchase was recent. A good early experience decides their future.',
        'Needs Attention' => 'Average recency and frequency — re-engage before they drift.',
        'At Risk' => 'Used to buy often but have gone quiet. Win them back now.',
        'Hibernating' => 'Low spend, long gone. Cheap reactivation offers only.',
        'Lost' => 'Longest gone, lowest engagement. Not worth heavy spend.',
    ];

    private const DRILL_DOWN_LIMIT = 100;

    /**
     * @return array<string, mixed>
     */
    public function getRfmData(int $userId, CarbonInterface $asOf, int $windowDays = 365): array
    {
        $customers = $this->rfmCustomers($userId, $asOf, $windowDays);

        if ($customers->isEmpty()) {
            return [
                'as_of' => $asOf->toDateString(),
                'window_days' => $windowDays,
                'totals' => [
                    'analyzed_customers' => 0,
                    'champions' => 0,
                    'at_risk' => 0,
                    'avg_lifetime_profit' => null,
                ],
                'segments' => $this->emptySegmentRows(),
                'segment_customers' => [],
            ];
        }

        $bySegment = $customers->groupBy('segment');
        $total = $customers->count();

        $segments = collect(array_keys(self::SEGMENTS))->map(function (string $segment) use ($bySegment, $total) {
            $members = $bySegment->get($segment, collect());

            return [
                'segment' => $segment,
                'description' => self::SEGMENTS[$segment],
                'count' => $members->count(),
                'pct' => $total > 0 ? round($members->count() / $total * 100, 1) : 0.0,
                'avg_recency_days' => $members->isEmpty() ? null : round($members->avg('recency_days'), 1),
                'avg_frequency' => $members->isEmpty() ? null : round($members->avg('frequency'), 1),
                'monetary_total' => round((float) $members->sum('monetary'), 2),
                'lifetime_profit_total' => round((float) $members->sum('lifetime_profit'), 2),
            ];
        })->all();

        $segmentCustomers = $bySegment->map(
            fn (Collection $members) => $members
                ->sortByDesc('monetary')
                ->take(self::DRILL_DOWN_LIMIT)
                ->values()
                ->all(),
        )->all();

        return [
            'as_of' => $asOf->toDateString(),
            'window_days' => $windowDays,
            'totals' => [
                'analyzed_customers' => $total,
                'champions' => $bySegment->get('Champions', collect())->count(),
                'at_risk' => $bySegment->get('At Risk', collect())->count(),
                'avg_lifetime_profit' => round((float) $customers->avg('lifetime_profit'), 2),
            ],
            'segments' => $segments,
            'segment_customers' => $segmentCustomers,
        ];
    }

    /**
     * Every analyzed customer with scores and segment — the full list
     * (no drill-down cap), used by getRfmData and the CSV export.
     *
     * @return \Illuminate\Support\Collection<int, array{customer_id: int, name: string, recency_days: int, frequency: int, monetary: float, lifetime_profit: float, r: int, f: int, m: int, segment: string}>
     */
    public function rfmCustomers(int $userId, CarbonInterface $asOf, int $windowDays = 365): Collection
    {
        $windowStart = $asOf->copy()->subDays($windowDays - 1);

        $rows = DailyCustomerMetric::forUser($userId)
            ->betweenDates($windowStart->toDateString(), $asOf->toDateString())
            ->groupBy('customer_id')
            ->select(
                'customer_id',
                DB::raw('MAX(IF(transactions > 0, date, NULL)) as last_purchase_date'),
                DB::raw('SUM(transactions) as frequency'),
                DB::raw('SUM(spend_total - refund_total) as monetary'),
                DB::raw('SUM(profit) as window_profit'),
            )
            ->havingRaw('SUM(transactions) > 0')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $lifetimeProfit = DailyCustomerMetric::forUser($userId)
            ->whereIn('customer_id', $rows->pluck('customer_id'))
            ->groupBy('customer_id')
            ->select('customer_id', DB::raw('SUM(profit) as lifetime_profit'))
            ->pluck('lifetime_profit', 'customer_id');

        $customerNames = DB::table('customers')
            ->whereIn('id', $rows->pluck('customer_id'))
            ->pluck('name', 'id');

        $recencyDays = $rows->mapWithKeys(fn ($row) => [
            $row->customer_id => (int) Carbon::parse($row->last_purchase_date)->diffInDays($asOf),
        ]);

        $recencyScores = $this->quintileScores($recencyDays, lowerIsBetter: true);
        $frequencyScores = $this->quintileScores($rows->pluck('frequency', 'customer_id')->map(fn ($v) => (float) $v));
        $monetaryScores = $this->quintileScores($rows->pluck('monetary', 'customer_id')->map(fn ($v) => (float) $v));

        return $rows->map(function ($row) use ($recencyDays, $recencyScores, $frequencyScores, $monetaryScores, $lifetimeProfit, $customerNames) {
            $r = $recencyScores[$row->customer_id];
            $f = $frequencyScores[$row->customer_id];
            $frequency = (int) $row->frequency;

            return [
                'customer_id' => (int) $row->customer_id,
                'name' => $customerNames[$row->customer_id] ?? 'Unknown',
                'recency_days' => $recencyDays[$row->customer_id],
                'frequency' => $frequency,
                'monetary' => round((float) $row->monetary, 2),
                'lifetime_profit' => round((float) ($lifetimeProfit[$row->customer_id] ?? 0), 2),
                'r' => $r,
                'f' => $f,
                'm' => $monetaryScores[$row->customer_id],
                'segment' => $this->segmentFor($r, $f, $frequency),
            ];
        });
    }

    /**
     * Ecommerce funnel: unique visitors → product viewers → cart adders
     * → checkout reachers → orders placed → orders completed.
     *
     * shop_visits has no tenant column (the storefront is
     * platform-level), so visit stages are platform-wide; the order
     * stages are tenant-scoped through the customer join. Completed =
     * PAID or PICKED_UP.
     *
     * @return array<string, mixed>
     */
    public function getFunnelData(int $userId, CarbonInterface $from, CarbonInterface $to): array
    {
        $bounds = [$from->toDateString().' 00:00:00', $to->toDateString().' 23:59:59'];

        $visitBase = ShopVisit::query()->whereBetween('created_at', $bounds);

        $visitors = (clone $visitBase)->distinct('visitor_id')->count('visitor_id');
        $productViewers = (clone $visitBase)->where('page_type', 'product')->distinct('visitor_id')->count('visitor_id');
        $cartAdders = (clone $visitBase)->where('action', 'add_to_cart')->distinct('visitor_id')->count('visitor_id');
        $checkoutReachers = (clone $visitBase)->where('page_type', 'checkout')->distinct('visitor_id')->count('visitor_id');

        $orderBase = EcommerceOrder::query()
            ->join('customers', 'customers.id', '=', 'ecommerce_orders.customer_id')
            ->where('customers.user_id', $userId)
            ->whereBetween('ecommerce_orders.created_at', $bounds);

        $ordersPlaced = (clone $orderBase)->count();
        $ordersCompleted = (clone $orderBase)
            ->whereIn('ecommerce_orders.status', [EcommerceOrder::STATUS_PAID, EcommerceOrder::STATUS_PICKED_UP])
            ->count();

        $stages = [
            ['stage' => 'Visitors', 'count' => $visitors],
            ['stage' => 'Viewed a Product', 'count' => $productViewers],
            ['stage' => 'Added to Cart', 'count' => $cartAdders],
            ['stage' => 'Reached Checkout', 'count' => $checkoutReachers],
            ['stage' => 'Placed an Order', 'count' => $ordersPlaced],
            ['stage' => 'Order Completed', 'count' => $ordersCompleted],
        ];

        foreach ($stages as $index => $stage) {
            $previous = $index === 0 ? null : $stages[$index - 1]['count'];
            $stages[$index]['pct_of_previous'] = ($previous === null || $previous === 0)
                ? null
                : round($stage['count'] / $previous * 100, 1);
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'stages' => $stages,
            'overall_conversion_pct' => $visitors > 0 ? round($ordersCompleted / $visitors * 100, 2) : null,
        ];
    }

    /**
     * Quintile (1-5) by percent rank. Ties share a score because the
     * rank counts strictly-better values only — important for small
     * shops where many customers share frequency=1.
     *
     * @param  \Illuminate\Support\Collection<int|string, int|float>  $values  keyed by customer_id
     * @return array<int|string, int>
     */
    protected function quintileScores(Collection $values, bool $lowerIsBetter = false): array
    {
        $count = $values->count();
        $sorted = $values->values()->sort()->values()->all();

        $firstIndex = [];
        $lastIndex = [];

        foreach ($sorted as $index => $value) {
            $key = (string) $value;
            $firstIndex[$key] ??= $index;
            $lastIndex[$key] = $index;
        }

        return $values->map(function ($value) use ($firstIndex, $lastIndex, $count, $lowerIsBetter) {
            $key = (string) $value;
            $worse = $lowerIsBetter
                ? $count - ($lastIndex[$key] + 1)
                : $firstIndex[$key];

            return min(5, (int) floor($worse / $count * 5) + 1);
        })->all();
    }

    /**
     * Classic R/F grid mapping, checked top-down. Monetary
     * deliberately doesn't drive the segment — it's surfaced as the
     * sort order and totals instead, which keeps the grid readable.
     */
    protected function segmentFor(int $r, int $f, int $rawFrequency): string
    {
        if ($r >= 4 && $f >= 4) {
            return 'Champions';
        }

        if ($r >= 3 && $f >= 3) {
            return 'Loyal';
        }

        if ($r >= 4) {
            return $rawFrequency <= 1 ? 'New' : 'Potential Loyalist';
        }

        if ($r === 3) {
            return 'Needs Attention';
        }

        if ($f >= 3) {
            return 'At Risk';
        }

        return $r === 2 ? 'Hibernating' : 'Lost';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function emptySegmentRows(): array
    {
        return collect(self::SEGMENTS)->map(fn (string $description, string $segment) => [
            'segment' => $segment,
            'description' => $description,
            'count' => 0,
            'pct' => 0.0,
            'avg_recency_days' => null,
            'avg_frequency' => null,
            'monetary_total' => 0.0,
            'lifetime_profit_total' => 0.0,
        ])->values()->all();
    }
}
