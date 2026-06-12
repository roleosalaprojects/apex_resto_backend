<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Settings\Store;
use App\Services\AiService;
use App\Services\ItemInsightsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ItemInsightsController extends Controller
{
    public function __construct(
        protected ItemInsightsService $insightsService,
        protected AiService $ai
    ) {}

    /**
     * Display the item insights dashboard.
     */
    public function index(): View
    {
        $stores = Store::where('user_id', auth()->user()->user_id)
            ->orderBy('name')
            ->get();

        $aiStatus = $this->ai->isAvailable();
        $aiProvider = $this->ai->activeProvider();

        return view('admin.insights.index', compact('stores', 'aiStatus', 'aiProvider'));
    }

    /**
     * Get item insights data for DataTable.
     */
    public function data(Request $request)
    {
        $userId = auth()->user()->user_id;
        $storeId = $request->input('store_id');
        $date = $request->input('date', Carbon::today()->toDateString());
        $refresh = $request->boolean('refresh');

        $date = Carbon::parse($date);

        $insights = $this->insightsService->getTopInsights($userId, $date, $storeId, $refresh);

        return DataTables::of($insights)
            ->addColumn('item_name', fn ($row) => $row->item?->name ?? 'Unknown')
            ->addColumn('score_bar', function ($row) {
                $color = $row->sellability_score >= 70 ? 'success' :
                    ($row->sellability_score >= 40 ? 'warning' : 'danger');

                return '<div class="d-flex align-items-center">
                    <span class="fw-bold me-2">'.$row->sellability_score.'</span>
                    <div class="progress w-100" style="height: 6px;">
                        <div class="progress-bar bg-'.$color.'" style="width: '.$row->sellability_score.'%"></div>
                    </div>
                </div>';
            })
            ->addColumn('factor_badges', function ($row) {
                $colors = [
                    'trending_up' => 'success',
                    'trending_down' => 'danger',
                    'high_volume' => 'primary',
                    'high_margin' => 'info',
                    'consistent_seller' => 'success',
                    'low_stock_risk' => 'danger',
                    'holiday_boost' => 'warning',
                    'weekend_boost' => 'info',
                    'payday_boost' => 'warning',
                    'weather_risk' => 'dark',
                ];

                $badges = '';
                foreach ($row->factors ?? [] as $factor) {
                    $color = $colors[$factor] ?? 'secondary';
                    $label = str_replace('_', ' ', $factor);
                    $badges .= '<span class="badge badge-light-'.$color.' me-1 mb-1">'.ucwords($label).'</span>';
                }

                return $badges;
            })
            ->addColumn('insight_text', fn ($row) => $row->ai_insight
                ? '<span class="text-gray-700" title="'.e($row->ai_insight).'">'.e(\Illuminate\Support\Str::limit($row->ai_insight, 60)).'</span>'
                : '<span class="text-muted">—</span>'
            )
            ->rawColumns(['score_bar', 'factor_badges', 'insight_text'])
            ->make(true);
    }

    /**
     * Get summary statistics for the stats cards.
     */
    public function summary(Request $request)
    {
        $userId = auth()->user()->user_id;
        $storeId = $request->input('store_id');
        $date = Carbon::parse($request->input('date', Carbon::today()->toDateString()));

        $insights = $this->insightsService->getTopInsights($userId, $date, $storeId);

        if ($insights->isEmpty()) {
            return response()->json([
                'top_item' => null,
                'top_score' => 0,
                'avg_score' => 0,
                'categories_count' => 0,
                'low_stock_count' => 0,
            ]);
        }

        $topItem = $insights->first();

        return response()->json([
            'top_item' => $topItem->item?->name ?? 'Unknown',
            'top_score' => $topItem->sellability_score,
            'avg_score' => round($insights->avg('sellability_score'), 1),
            'categories_count' => $insights->pluck('category_name')->filter()->unique()->count(),
            'low_stock_count' => $insights->filter(function ($insight) {
                return in_array('low_stock_risk', $insight->factors ?? []);
            })->count(),
        ]);
    }
}
