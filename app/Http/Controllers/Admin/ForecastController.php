<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Forecast;
use App\Models\InventoryManagement\ReorderSuggestion;
use App\Models\Settings\Store;
use App\Services\AiService;
use App\Services\DemandForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class ForecastController extends Controller
{
    public function __construct(
        protected DemandForecastService $forecastService,
        protected AiService $ai
    ) {}

    /**
     * Display the forecast dashboard.
     */
    public function index(): View
    {
        $stores = Store::where('user_id', auth()->user()->user_id)
            ->orderBy('name')
            ->get();

        $aiStatus = $this->ai->isAvailable();
        $aiProvider = $this->ai->activeProvider();

        return view('admin.forecast.index', compact('stores', 'aiStatus', 'aiProvider'));
    }

    /**
     * Get daily sales forecast data.
     */
    public function dailySales(Request $request): JsonResponse
    {
        $userId = auth()->user()->user_id;
        $days = (int) $request->input('days', 7);
        $storeId = $request->input('store_id');
        $refresh = $request->boolean('refresh');

        if ($refresh) {
            $forecasts = $this->forecastService->forecastDailySales($userId, $days, $storeId);
        } else {
            $forecasts = Forecast::where('user_id', $userId)
                ->where('forecast_type', 'daily_sales')
                ->where('forecast_date', '>=', now()->toDateString())
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->orderBy('forecast_date')
                ->limit($days)
                ->get();

            if ($forecasts->count() < $days) {
                $forecasts = $this->forecastService->forecastDailySales($userId, $days, $storeId);
            }
        }

        $chartData = [
            'categories' => $forecasts->map(fn ($f) => $f->forecast_date->format('M d'))->toArray(),
            'predicted' => $forecasts->map(fn ($f) => round($f->predicted_value, 2))->toArray(),
            'lowerBound' => $forecasts->map(fn ($f) => round($f->lower_bound, 2))->toArray(),
            'upperBound' => $forecasts->map(fn ($f) => round($f->upper_bound, 2))->toArray(),
            'confidence' => $forecasts->map(fn ($f) => round($f->confidence, 1))->toArray(),
        ];

        $totalPredicted = $forecasts->sum('predicted_value');
        $avgConfidence = $forecasts->avg('confidence');
        $aiInsight = $forecasts->first()?->ai_insight;

        return response()->json([
            'chart' => $chartData,
            'total_predicted' => $totalPredicted,
            'avg_confidence' => round($avgConfidence, 1),
            'ai_insight' => $aiInsight,
            'forecasts' => $forecasts->map(fn ($f) => [
                'date' => $f->forecast_date->format('Y-m-d'),
                'day' => $f->forecast_date->dayName,
                'predicted' => $f->predicted_value,
                'confidence' => $f->confidence,
                'lower_bound' => $f->lower_bound,
                'upper_bound' => $f->upper_bound,
            ]),
        ]);
    }

    /**
     * Get reorder suggestions data for DataTable.
     */
    public function reorderSuggestions(Request $request)
    {
        $userId = auth()->user()->user_id;
        $storeId = $request->input('store_id');

        if ($request->boolean('refresh')) {
            $this->forecastService->generateReorderSuggestions($userId, $storeId);
        }

        $query = ReorderSuggestion::with('item:id,name,barcode', 'store:id,name')
            ->where('user_id', $userId)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->orderByRaw("FIELD(urgency, 'critical', 'high', 'medium', 'low')");

        return DataTables::of($query)
            ->addColumn('item_name', fn ($row) => $row->item?->name ?? 'Unknown')
            ->addColumn('store_name', fn ($row) => $row->store?->name ?? 'All Stores')
            ->addColumn('urgency_text', fn ($row) => strtoupper($row->urgency))
            ->addColumn('urgency_badge', function ($row) {
                $colors = [
                    'critical' => 'danger',
                    'high' => 'warning',
                    'medium' => 'info',
                    'low' => 'secondary',
                ];
                $color = $colors[$row->urgency] ?? 'secondary';

                return '<span class="badge badge-light-'.$color.'">'.strtoupper($row->urgency).'</span>';
            })
            ->addColumn('action', function ($row) {
                if ($row->is_acknowledged) {
                    return '<span class="text-muted">Acknowledged</span>';
                }

                return '<button class="btn btn-sm btn-light-success acknowledge-btn" data-id="'.$row->id.'">
                    <i class="ki-outline ki-check fs-4"></i> Acknowledge
                </button>';
            })
            ->rawColumns(['urgency_badge', 'action'])
            ->make(true);
    }

    /**
     * Export all reorder suggestions to CSV/Excel.
     */
    public function exportReorderSuggestions(Request $request): StreamedResponse
    {
        $userId = auth()->user()->user_id;
        $storeId = $request->input('store_id');
        $format = $request->input('format', 'csv');

        $suggestions = ReorderSuggestion::with('item:id,name,barcode', 'store:id,name')
            ->where('user_id', $userId)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->orderByRaw("FIELD(urgency, 'critical', 'high', 'medium', 'low')")
            ->get();

        $filename = 'reorder_suggestions_'.date('Y-m-d_His');

        if ($format === 'excel') {
            // Excel XML format (opens in Excel without additional packages)
            return $this->exportAsExcelXml($suggestions, $filename);
        }

        // Default CSV format
        return $this->exportAsCsv($suggestions, $filename);
    }

    /**
     * Export as CSV.
     */
    protected function exportAsCsv($suggestions, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($suggestions) {
            $handle = fopen('php://output', 'w');

            // Add BOM for Excel UTF-8 compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header row
            fputcsv($handle, [
                'Item Name',
                'Barcode',
                'Store',
                'Current Stock',
                'Predicted Demand',
                'Days Until Stockout',
                'Suggested Quantity',
                'Urgency',
                'AI Reason',
                'Acknowledged',
            ]);

            // Data rows
            foreach ($suggestions as $row) {
                fputcsv($handle, [
                    $row->item?->name ?? 'Unknown',
                    $row->item?->barcode ?? '',
                    $row->store?->name ?? 'All Stores',
                    number_format($row->current_stock, 2),
                    number_format($row->predicted_demand, 2),
                    $row->days_until_stockout,
                    number_format($row->suggested_quantity, 2),
                    strtoupper($row->urgency),
                    $row->ai_reason ?? '',
                    $row->is_acknowledged ? 'Yes' : 'No',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export as Excel XML (SpreadsheetML) format.
     */
    protected function exportAsExcelXml($suggestions, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.xls"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($suggestions) {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
                  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Styles>
                    <Style ss:ID="Header">
                        <Font ss:Bold="1"/>
                    </Style>
                    <Style ss:ID="Critical">
                        <Font ss:Color="#DC3545" ss:Bold="1"/>
                    </Style>
                    <Style ss:ID="High">
                        <Font ss:Color="#FD7E14" ss:Bold="1"/>
                    </Style>
                    <Style ss:ID="Medium">
                        <Font ss:Color="#0D6EFD"/>
                    </Style>
                    <Style ss:ID="Number">
                        <NumberFormat ss:Format="₱#,##0.00"/>
                    </Style>
                  </Styles>';
            echo '<Worksheet ss:Name="Reorder Suggestions">';
            echo '<Table>';

            // Header row
            echo '<Row ss:StyleID="Header">';
            $headerCols = ['Item Name', 'Barcode', 'Store', 'Current Stock', 'Predicted Demand', 'Days Until Stockout', 'Suggested Quantity', 'Urgency', 'AI Reason', 'Acknowledged'];
            foreach ($headerCols as $col) {
                echo '<Cell><Data ss:Type="String">'.htmlspecialchars($col).'</Data></Cell>';
            }
            echo '</Row>';

            // Data rows
            foreach ($suggestions as $row) {
                $style = match ($row->urgency) {
                    'critical' => 'Critical',
                    'high' => 'High',
                    'medium' => 'Medium',
                    default => ''
                };

                echo '<Row'.($style ? ' ss:StyleID="'.$style.'"' : '').'>';
                echo '<Cell><Data ss:Type="String">'.htmlspecialchars($row->item?->name ?? 'Unknown').'</Data></Cell>';
                echo '<Cell><Data ss:Type="String">'.htmlspecialchars($row->item?->barcode ?? '').'</Data></Cell>';
                echo '<Cell><Data ss:Type="String">'.htmlspecialchars($row->store?->name ?? 'All Stores').'</Data></Cell>';
                echo '<Cell ss:StyleID="Number"><Data ss:Type="Number">'.$row->current_stock.'</Data></Cell>';
                echo '<Cell ss:StyleID="Number"><Data ss:Type="Number">'.$row->predicted_demand.'</Data></Cell>';
                echo '<Cell><Data ss:Type="Number">'.$row->days_until_stockout.'</Data></Cell>';
                echo '<Cell ss:StyleID="Number"><Data ss:Type="Number">'.$row->suggested_quantity.'</Data></Cell>';
                echo '<Cell><Data ss:Type="String">'.strtoupper($row->urgency).'</Data></Cell>';
                echo '<Cell><Data ss:Type="String">'.htmlspecialchars($row->ai_reason ?? '').'</Data></Cell>';
                echo '<Cell><Data ss:Type="String">'.($row->is_acknowledged ? 'Yes' : 'No').'</Data></Cell>';
                echo '</Row>';
            }

            echo '</Table>';
            echo '</Worksheet>';
            echo '</Workbook>';
        }, 200, $headers);
    }

    /**
     * Get reorder summary counts.
     */
    public function reorderSummary(Request $request): JsonResponse
    {
        $userId = auth()->user()->user_id;
        $storeId = $request->input('store_id');

        $suggestions = ReorderSuggestion::where('user_id', $userId)
            ->where('is_acknowledged', false)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->get();

        return response()->json([
            'total' => $suggestions->count(),
            'critical' => $suggestions->where('urgency', 'critical')->count(),
            'high' => $suggestions->where('urgency', 'high')->count(),
            'medium' => $suggestions->where('urgency', 'medium')->count(),
            'low' => $suggestions->where('urgency', 'low')->count(),
        ]);
    }

    /**
     * Acknowledge a reorder suggestion.
     */
    public function acknowledge(int $id): JsonResponse
    {
        $suggestion = ReorderSuggestion::where('user_id', auth()->user()->user_id)
            ->findOrFail($id);

        $suggestion->update(['is_acknowledged' => true]);

        return response()->json(['success' => true]);
    }

    /**
     * Get sales patterns data.
     */
    public function patterns(Request $request): JsonResponse
    {
        $userId = auth()->user()->user_id;
        $days = (int) $request->input('days', 30);
        $storeId = $request->input('store_id');

        $analysis = $this->forecastService->analyzeSalesPatterns($userId, $days, $storeId);

        $dayNames = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $dayOfWeekChart = [
            'categories' => [],
            'sales' => [],
            'transactions' => [],
        ];

        foreach ($analysis['patterns']['day_of_week'] ?? [] as $dayNum => $stats) {
            $dayOfWeekChart['categories'][] = $dayNames[$dayNum] ?? "Day $dayNum";
            $dayOfWeekChart['sales'][] = round($stats['avg_sales'], 2);
            $dayOfWeekChart['transactions'][] = round($stats['avg_transactions'], 1);
        }

        return response()->json([
            'trend' => $analysis['patterns']['overall_trend'] ?? 'unknown',
            'average_daily_sales' => round($analysis['patterns']['average_daily_sales'] ?? 0, 2),
            'peak_day' => $analysis['patterns']['peak_day'] ?? null,
            'lowest_day' => $analysis['patterns']['lowest_day'] ?? null,
            'day_of_week_chart' => $dayOfWeekChart,
            'ai_insight' => $analysis['insight'],
        ]);
    }

    /**
     * Check AI service status.
     */
    public function aiStatus(): JsonResponse
    {
        return response()->json([
            'available' => $this->ai->isAvailable(),
            'provider' => $this->ai->activeProvider(),
        ]);
    }
}
