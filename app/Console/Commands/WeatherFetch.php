<?php

namespace App\Console\Commands;

use App\Models\Settings\Store;
use App\Services\WeatherService;
use Illuminate\Console\Command;

class WeatherFetch extends Command
{
    protected $signature = 'weather:fetch
                            {--store= : Specific store ID to fetch weather for}
                            {--user= : User ID to filter stores}';

    protected $description = 'Fetch and cache weather forecasts for store locations';

    public function handle(WeatherService $weather): int
    {
        if (! $weather->isAvailable()) {
            $this->warn('OpenWeatherMap API key not configured. Set OPENWEATHERMAP_API_KEY in .env');

            return Command::FAILURE;
        }

        $query = Store::query()
            ->where('status', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($storeId = $this->option('store')) {
            $query->where('id', $storeId);
        }

        if ($userId = $this->option('user')) {
            $query->where('user_id', $userId);
        }

        $stores = $query->get();

        if ($stores->isEmpty()) {
            $this->info('No stores with location data found.');

            return Command::SUCCESS;
        }

        // Deduplicate by coordinates to avoid redundant API calls
        $uniqueLocations = $stores->unique(function ($store) {
            return "{$store->latitude},{$store->longitude}";
        });

        $this->info("Fetching weather for {$uniqueLocations->count()} unique location(s) ({$stores->count()} store(s))...");

        $success = 0;
        $failed = 0;

        foreach ($uniqueLocations as $store) {
            $result = $weather->refreshForecast((float) $store->latitude, (float) $store->longitude);

            if ($result !== null) {
                $this->line("  [{$store->name}] {$store->latitude}, {$store->longitude} - ".count($result).' days fetched');
                $success++;
            } else {
                $this->error("  [{$store->name}] Failed to fetch weather");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Success: {$success}, Failed: {$failed}");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
