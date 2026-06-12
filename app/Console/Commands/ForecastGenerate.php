<?php

namespace App\Console\Commands;

use App\Services\AiService;
use App\Services\DemandForecastService;
use Illuminate\Console\Command;

class ForecastGenerate extends Command
{
    protected $signature = 'forecast:generate
                            {--user= : User ID to generate forecasts for}
                            {--store= : Store ID (optional)}
                            {--days=7 : Number of days to forecast}
                            {--reorder : Generate reorder suggestions}
                            {--patterns : Analyze sales patterns}
                            {--all : Run all forecast types}';

    protected $description = 'Generate demand forecasts using historical data and AI insights';

    public function handle(DemandForecastService $forecastService, AiService $ai): int
    {
        $userId = $this->option('user') ?? 1;
        $storeId = $this->option('store');
        $days = (int) $this->option('days');

        $this->info('🔮 Demand Forecasting System');
        $this->newLine();

        // Check AI availability
        if ($ai->isAvailable()) {
            $this->info('✅ AI provider ('.$ai->activeProvider().') is available - AI insights enabled');
        } else {
            $this->warn('⚠️  No AI provider available - running without AI insights');
        }
        $this->newLine();

        // Generate daily sales forecast
        if ($this->option('all') || ! $this->option('reorder') && ! $this->option('patterns')) {
            $this->generateDailySalesForecast($forecastService, $userId, $days, $storeId);
        }

        // Generate reorder suggestions
        if ($this->option('all') || $this->option('reorder')) {
            $this->generateReorderSuggestions($forecastService, $userId, $storeId);
        }

        // Analyze patterns
        if ($this->option('all') || $this->option('patterns')) {
            $this->analyzeSalesPatterns($forecastService, $userId, $storeId);
        }

        $this->newLine();
        $this->info('✅ Forecast generation complete!');

        return Command::SUCCESS;
    }

    protected function generateDailySalesForecast(DemandForecastService $service, int $userId, int $days, ?int $storeId): void
    {
        $this->info("📊 Generating {$days}-day sales forecast...");

        $forecasts = $service->forecastDailySales($userId, $days, $storeId);

        if ($forecasts->isEmpty()) {
            $this->warn('   No historical data available for forecasting');

            return;
        }

        $this->table(
            ['Date', 'Day', 'Predicted Sales', 'Confidence', 'Range'],
            $forecasts->map(fn ($f) => [
                $f->forecast_date->format('M d, Y'),
                $f->forecast_date->dayName,
                '₱'.number_format($f->predicted_value, 2),
                $f->confidence.'%',
                '₱'.number_format($f->lower_bound, 2).' - ₱'.number_format($f->upper_bound, 2),
            ])
        );

        $totalPredicted = $forecasts->sum('predicted_value');
        $this->line("   Total predicted for {$days} days: ₱".number_format($totalPredicted, 2));

        // Show AI insight if available
        $insight = $forecasts->first()?->ai_insight;
        if ($insight) {
            $this->newLine();
            $this->info('🤖 AI Insight:');
            $this->line('   '.wordwrap($insight, 80, "\n   "));
        }
    }

    protected function generateReorderSuggestions(DemandForecastService $service, int $userId, ?int $storeId): void
    {
        $this->newLine();
        $this->info('📦 Generating reorder suggestions...');

        $suggestions = $service->generateReorderSuggestions($userId, $storeId);

        if ($suggestions->isEmpty()) {
            $this->line('   No reorder suggestions at this time');

            return;
        }

        $critical = $suggestions->where('urgency', 'critical');
        $high = $suggestions->where('urgency', 'high');

        if ($critical->isNotEmpty()) {
            $this->error("   🚨 {$critical->count()} CRITICAL items need immediate attention!");
        }

        if ($high->isNotEmpty()) {
            $this->warn("   ⚠️  {$high->count()} HIGH priority items");
        }

        $this->table(
            ['Item', 'Stock', 'Predicted Demand', 'Days Left', 'Reorder Qty', 'Urgency'],
            $suggestions->take(10)->map(fn ($s) => [
                $s->item?->name ?? 'Item #'.$s->item_id,
                number_format($s->current_stock, 2),
                number_format($s->predicted_demand, 2),
                $s->days_until_stockout,
                number_format($s->suggested_quantity, 2),
                strtoupper($s->urgency),
            ])
        );

        // Show AI reasons for critical items
        $criticalWithReasons = $suggestions->where('urgency', 'critical')->whereNotNull('ai_reason');
        if ($criticalWithReasons->isNotEmpty()) {
            $this->newLine();
            $this->info('🤖 AI Recommendations for Critical Items:');
            foreach ($criticalWithReasons->take(3) as $s) {
                $this->line("   • {$s->item?->name}: {$s->ai_reason}");
            }
        }
    }

    protected function analyzeSalesPatterns(DemandForecastService $service, int $userId, ?int $storeId): void
    {
        $this->newLine();
        $this->info('🔍 Analyzing sales patterns (last 30 days)...');

        $analysis = $service->analyzeSalesPatterns($userId, 30, $storeId);

        if (empty($analysis['patterns'])) {
            $this->line('   Insufficient data for pattern analysis');

            return;
        }

        $patterns = $analysis['patterns'];

        $this->line('   📈 Overall Trend: '.ucfirst($patterns['overall_trend']));
        $this->line('   💰 Average Daily Sales: ₱'.number_format($patterns['average_daily_sales'], 2));

        if ($patterns['peak_day']) {
            $this->line("   🔥 Best Day: {$patterns['peak_day']['date']} (₱".number_format($patterns['peak_day']['total'], 2).')');
        }

        // Day of week analysis
        $this->newLine();
        $this->info('📅 Sales by Day of Week:');
        $dayNames = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        foreach ($patterns['day_of_week'] as $dayNum => $stats) {
            $bar = str_repeat('█', min(20, (int) ($stats['avg_sales'] / ($patterns['average_daily_sales'] ?: 1) * 10)));
            $this->line("   {$dayNames[$dayNum]}: ₱".number_format($stats['avg_sales'], 2)." {$bar}");
        }

        // AI insight
        if ($analysis['insight']) {
            $this->newLine();
            $this->info('🤖 AI Pattern Analysis:');
            $this->line('   '.wordwrap($analysis['insight'], 80, "\n   "));
        }
    }
}
