<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    protected string $apiKey;

    protected string $baseUrl;

    protected int $timeout;

    /**
     * Weather condition ID ranges from OpenWeatherMap.
     * 2xx = Thunderstorm, 3xx = Drizzle, 5xx = Rain, 6xx = Snow, 7xx = Atmosphere, 800 = Clear, 80x = Clouds
     */
    protected const THUNDERSTORM_RANGE = [200, 299];

    public function __construct()
    {
        $this->apiKey = config('services.openweathermap.api_key', '');
        $this->baseUrl = config('services.openweathermap.base_url', 'https://api.openweathermap.org/data/2.5');
        $this->timeout = (int) config('services.openweathermap.timeout', 15);
    }

    /**
     * Check if the weather service is available (API key configured).
     */
    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Fetch 5-day/3-hour forecast and cache for 6 hours.
     *
     * @return array<string, array{max_temp: float, total_rain: float, max_wind: float, dominant_condition: int, condition_name: string}>|null
     */
    public function getForecast(float $lat, float $lng): ?array
    {
        $cacheKey = "weather_forecast:{$lat},{$lng}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($lat, $lng) {
            return $this->fetchAndTransformForecast($lat, $lng);
        });
    }

    /**
     * Get the weather sales factor for a specific date and location.
     * Returns 1.0 (neutral) if weather data is unavailable.
     */
    public function getWeatherSalesFactor(float $lat, float $lng, Carbon $date): float
    {
        $forecast = $this->getForecast($lat, $lng);

        if (! $forecast) {
            return 1.0;
        }

        $dateKey = $date->format('Y-m-d');

        if (! isset($forecast[$dateKey])) {
            return 1.0;
        }

        return $this->calculateWeatherSalesFactor($forecast[$dateKey], $date);
    }

    /**
     * Get full weather info for a specific date (for display in factors).
     *
     * @return array{condition: string, max_temp: float, total_rain: float, max_wind: float, sales_factor: float}|null
     */
    public function getWeatherInfo(float $lat, float $lng, Carbon $date): ?array
    {
        $forecast = $this->getForecast($lat, $lng);

        if (! $forecast) {
            return null;
        }

        $dateKey = $date->format('Y-m-d');

        if (! isset($forecast[$dateKey])) {
            return null;
        }

        $day = $forecast[$dateKey];
        $factor = $this->calculateWeatherSalesFactor($day, $date);

        return [
            'condition' => $day['condition_name'],
            'max_temp' => $day['max_temp'],
            'total_rain' => $day['total_rain'],
            'max_wind' => $day['max_wind'],
            'sales_factor' => $factor,
        ];
    }

    /**
     * Clear cache and re-fetch forecast for a location.
     */
    public function refreshForecast(float $lat, float $lng): ?array
    {
        $cacheKey = "weather_forecast:{$lat},{$lng}";
        Cache::forget($cacheKey);

        return $this->getForecast($lat, $lng);
    }

    /**
     * Fetch forecast from OpenWeatherMap and transform into daily summaries.
     */
    protected function fetchAndTransformForecast(float $lat, float $lng): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/forecast", [
                    'lat' => $lat,
                    'lon' => $lng,
                    'appid' => $this->apiKey,
                    'units' => 'metric',
                ]);

            if (! $response->successful()) {
                Log::warning('OpenWeatherMap API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $this->transformForecastResponse($response->json());
        } catch (\Exception $e) {
            Log::warning('OpenWeatherMap connection error', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Aggregate 3-hour OWM intervals into daily summaries.
     *
     * @return array<string, array{max_temp: float, total_rain: float, max_wind: float, dominant_condition: int, condition_name: string}>
     */
    protected function transformForecastResponse(array $response): array
    {
        $dailyData = [];

        foreach ($response['list'] ?? [] as $interval) {
            $date = Carbon::createFromTimestamp($interval['dt'])->format('Y-m-d');

            if (! isset($dailyData[$date])) {
                $dailyData[$date] = [
                    'temps' => [],
                    'rain' => 0.0,
                    'winds' => [],
                    'conditions' => [],
                ];
            }

            $dailyData[$date]['temps'][] = $interval['main']['temp'] ?? 0;
            $dailyData[$date]['rain'] += $interval['rain']['3h'] ?? 0;
            $dailyData[$date]['winds'][] = ($interval['wind']['speed'] ?? 0) * 3.6; // m/s to kph
            $dailyData[$date]['conditions'][] = $interval['weather'][0]['id'] ?? 800;
        }

        $result = [];

        foreach ($dailyData as $date => $data) {
            $conditionCounts = array_count_values($data['conditions']);
            arsort($conditionCounts);
            $dominantCondition = array_key_first($conditionCounts);

            $result[$date] = [
                'max_temp' => ! empty($data['temps']) ? max($data['temps']) : 0,
                'total_rain' => round($data['rain'], 2),
                'max_wind' => ! empty($data['winds']) ? round(max($data['winds']), 2) : 0,
                'dominant_condition' => $dominantCondition,
                'condition_name' => $this->getConditionName($dominantCondition),
            ];
        }

        return $result;
    }

    /**
     * Calculate the weather sales factor based on weather conditions.
     * Takes the minimum (worst) factor when multiple conditions overlap.
     */
    protected function calculateWeatherSalesFactor(array $dayData, Carbon $date): float
    {
        $factors = [];

        // Rain factor
        $rain = $dayData['total_rain'];
        if ($rain > 50) {
            $factors[] = 0.45;
        } elseif ($rain > 20) {
            $factors[] = 0.60;
        } elseif ($rain > 7) {
            $factors[] = 0.75;
        } elseif ($rain > 2) {
            $factors[] = 0.85;
        } elseif ($rain > 0) {
            $factors[] = 0.90;
        }

        // Thunderstorm factor (OWM condition code 2xx)
        $condition = $dayData['dominant_condition'];
        if ($condition >= self::THUNDERSTORM_RANGE[0] && $condition <= self::THUNDERSTORM_RANGE[1]) {
            $factors[] = 0.65;
        }

        // Temperature factor
        $maxTemp = $dayData['max_temp'];
        if ($maxTemp > 38) {
            $factors[] = 0.80;
        } elseif ($maxTemp >= 35) {
            $factors[] = 0.90;
        }

        // Typhoon/wind factor
        $maxWind = $dayData['max_wind']; // in kph
        if ($maxWind > 220) {
            $factors[] = 0.05; // Signal 5
        } elseif ($maxWind > 170) {
            $factors[] = 0.15; // Signal 4
        } elseif ($maxWind > 120) {
            $factors[] = 0.30; // Signal 3
        } elseif ($maxWind > 60) {
            $factors[] = 0.50; // Signal 2
        } elseif ($maxWind >= 30) {
            // Signal 1 only during typhoon season (June-November)
            $month = $date->month;
            if ($month >= 6 && $month <= 11) {
                $factors[] = 0.70;
            }
        }

        if (empty($factors)) {
            return 1.0;
        }

        return min($factors);
    }

    /**
     * Get a human-readable condition name from OWM weather code.
     */
    protected function getConditionName(int $conditionId): string
    {
        return match (true) {
            $conditionId >= 200 && $conditionId <= 299 => 'Thunderstorm',
            $conditionId >= 300 && $conditionId <= 399 => 'Drizzle',
            $conditionId >= 500 && $conditionId <= 504 => 'Rain',
            $conditionId === 511 => 'Freezing Rain',
            $conditionId >= 520 && $conditionId <= 531 => 'Heavy Rain',
            $conditionId >= 600 && $conditionId <= 699 => 'Snow',
            $conditionId >= 700 && $conditionId <= 799 => 'Atmosphere',
            $conditionId === 800 => 'Clear',
            $conditionId >= 801 && $conditionId <= 804 => 'Cloudy',
            default => 'Unknown',
        };
    }
}
