<?php

namespace App\Http\Controllers\Admin\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\Ecommerce\ShopVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopAnalyticsController extends Controller
{
    public function index(): View
    {
        $today = ShopVisit::today();
        $thisWeek = ShopVisit::thisWeek();
        $thisMonth = ShopVisit::thisMonth();

        $stats = [
            'today' => [
                'visits' => $today->count(),
                'unique' => ShopVisit::today()->distinct()->count('visitor_id'),
            ],
            'week' => [
                'visits' => $thisWeek->count(),
                'unique' => ShopVisit::thisWeek()->distinct()->count('visitor_id'),
            ],
            'month' => [
                'visits' => $thisMonth->count(),
                'unique' => ShopVisit::thisMonth()->distinct()->count('visitor_id'),
            ],
        ];

        return view('admin.analytics.visitors', compact('stats'));
    }

    public function data(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from', now()->subDays(7)->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());
        $deviceType = $request->input('device_type');
        $pageType = $request->input('page_type');

        $query = ShopVisit::query()
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo);

        if ($deviceType) {
            $query->where('device_type', $deviceType);
        }

        if ($pageType) {
            $query->where('page_type', $pageType);
        }

        $visits = $query->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'visitor_id' => substr($v->visitor_id, 0, 8).'...',
                'customer' => $v->customer?->name ?? 'Guest',
                'page' => $v->page_visited,
                'page_type' => $v->page_type,
                'device' => $v->device_type,
                'browser' => $v->browser,
                'platform' => $v->platform,
                'referrer' => $v->referrer_domain,
                'ip' => $v->ip_address,
                'created_at' => $v->created_at->format('M d, Y H:i'),
            ]);

        return response()->json(['data' => $visits]);
    }

    public function charts(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 7);
        $startDate = now()->subDays($days - 1)->startOfDay();

        // Visits per day
        $visitsPerDay = ShopVisit::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as visits'),
            DB::raw('COUNT(DISTINCT visitor_id) as unique_visitors')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Device breakdown
        $deviceBreakdown = ShopVisit::select('device_type', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('device_type')
            ->groupBy('device_type')
            ->get();

        // Page type breakdown
        $pageBreakdown = ShopVisit::select('page_type', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('page_type')
            ->get();

        // Top pages
        $topPages = ShopVisit::select('page_visited', DB::raw('COUNT(*) as views'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('page_visited')
            ->orderByDesc('views')
            ->limit(10)
            ->get();

        // Hourly distribution (today)
        $hourlyDistribution = ShopVisit::select(
            DB::raw('HOUR(created_at) as hour'),
            DB::raw('COUNT(*) as visits')
        )
            ->whereDate('created_at', today())
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // Top referrers
        $topReferrers = ShopVisit::select('referrer_domain', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('referrer_domain')
            ->where('referrer_domain', '!=', '')
            ->groupBy('referrer_domain')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Browser breakdown
        $browserBreakdown = ShopVisit::select('browser', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('browser')
            ->groupBy('browser')
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'visits_per_day' => $visitsPerDay,
            'device_breakdown' => $deviceBreakdown,
            'page_breakdown' => $pageBreakdown,
            'top_pages' => $topPages,
            'hourly_distribution' => $hourlyDistribution,
            'top_referrers' => $topReferrers,
            'browser_breakdown' => $browserBreakdown,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $dateFrom = $request->input('date_from', now()->subDays(7)->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        $filename = "shop_visitors_{$dateFrom}_to_{$dateTo}.csv";

        return response()->streamDownload(function () use ($dateFrom, $dateTo) {
            $handle = fopen('php://output', 'w');

            // Headers
            fputcsv($handle, [
                'ID',
                'Visitor ID',
                'Customer',
                'Page',
                'Page Type',
                'Device',
                'Browser',
                'Platform',
                'Referrer',
                'IP Address',
                'UTM Source',
                'UTM Medium',
                'UTM Campaign',
                'Visited At',
            ]);

            ShopVisit::query()
                ->whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo)
                ->with('customer')
                ->orderByDesc('created_at')
                ->chunk(500, function ($visits) use ($handle) {
                    foreach ($visits as $v) {
                        fputcsv($handle, [
                            $v->id,
                            $v->visitor_id,
                            $v->customer?->name ?? 'Guest',
                            $v->page_visited,
                            $v->page_type,
                            $v->device_type,
                            $v->browser,
                            $v->platform,
                            $v->referrer_domain,
                            $v->ip_address,
                            $v->utm_source,
                            $v->utm_medium,
                            $v->utm_campaign,
                            $v->created_at->format('Y-m-d H:i:s'),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
