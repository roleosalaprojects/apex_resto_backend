<?php

namespace Tests\Feature\Services;

use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherServiceTest extends TestCase
{
    private WeatherService $weatherService;

    /**
     * Sample OWM forecast API response with 3-hour intervals.
     */
    private function sampleForecastResponse(array $overrides = []): array
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        return [
            'list' => [
                // Today interval 1 - Clear
                [
                    'dt' => $today->copy()->setHour(9)->timestamp,
                    'main' => ['temp' => $overrides['temp_1'] ?? 32.0],
                    'wind' => ['speed' => ($overrides['wind_kph_1'] ?? 10) / 3.6],
                    'rain' => ['3h' => $overrides['rain_1'] ?? 0],
                    'weather' => [['id' => $overrides['condition_1'] ?? 800]],
                ],
                // Today interval 2 - Clear
                [
                    'dt' => $today->copy()->setHour(12)->timestamp,
                    'main' => ['temp' => $overrides['temp_2'] ?? 34.0],
                    'wind' => ['speed' => ($overrides['wind_kph_2'] ?? 12) / 3.6],
                    'rain' => ['3h' => $overrides['rain_2'] ?? 0],
                    'weather' => [['id' => $overrides['condition_2'] ?? 800]],
                ],
                // Tomorrow interval 1
                [
                    'dt' => $tomorrow->copy()->setHour(9)->timestamp,
                    'main' => ['temp' => $overrides['temp_3'] ?? 30.0],
                    'wind' => ['speed' => ($overrides['wind_kph_3'] ?? 8) / 3.6],
                    'rain' => ['3h' => $overrides['rain_3'] ?? 0],
                    'weather' => [['id' => $overrides['condition_3'] ?? 802]],
                ],
                // Tomorrow interval 2
                [
                    'dt' => $tomorrow->copy()->setHour(12)->timestamp,
                    'main' => ['temp' => $overrides['temp_4'] ?? 31.0],
                    'wind' => ['speed' => ($overrides['wind_kph_4'] ?? 10) / 3.6],
                    'rain' => ['3h' => $overrides['rain_4'] ?? 0],
                    'weather' => [['id' => $overrides['condition_4'] ?? 802]],
                ],
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.openweathermap.api_key', 'test-api-key');
        Config::set('services.openweathermap.base_url', 'https://api.openweathermap.org/data/2.5');
        Config::set('services.openweathermap.timeout', 15);

        $this->weatherService = new WeatherService;
    }

    public function test_is_available_returns_true_when_api_key_configured(): void
    {
        $this->assertTrue($this->weatherService->isAvailable());
    }

    public function test_is_available_returns_false_when_no_api_key(): void
    {
        Config::set('services.openweathermap.api_key', '');
        $service = new WeatherService;

        $this->assertFalse($service->isAvailable());
    }

    public function test_get_forecast_returns_daily_summaries(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse()),
        ]);

        $forecast = $this->weatherService->getForecast(14.5995, 120.9842);

        $this->assertNotNull($forecast);
        $this->assertIsArray($forecast);
        $this->assertArrayHasKey(Carbon::today()->format('Y-m-d'), $forecast);
        $this->assertArrayHasKey(Carbon::tomorrow()->format('Y-m-d'), $forecast);

        $todayData = $forecast[Carbon::today()->format('Y-m-d')];
        $this->assertArrayHasKey('max_temp', $todayData);
        $this->assertArrayHasKey('total_rain', $todayData);
        $this->assertArrayHasKey('max_wind', $todayData);
        $this->assertArrayHasKey('dominant_condition', $todayData);
        $this->assertArrayHasKey('condition_name', $todayData);
    }

    public function test_get_forecast_caches_result(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse()),
        ]);

        $this->weatherService->getForecast(14.5995, 120.9842);
        $this->weatherService->getForecast(14.5995, 120.9842);

        Http::assertSentCount(1);
    }

    public function test_refresh_forecast_clears_cache(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse()),
        ]);

        $this->weatherService->getForecast(14.5995, 120.9842);
        $this->weatherService->refreshForecast(14.5995, 120.9842);

        Http::assertSentCount(2);
    }

    public function test_clear_weather_returns_factor_1(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse()),
        ]);

        $factor = $this->weatherService->getWeatherSalesFactor(14.5995, 120.9842, Carbon::today());

        $this->assertEquals(1.0, $factor);
    }

    public function test_heavy_rain_returns_reduced_factor(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse([
                'rain_1' => 15.0,
                'rain_2' => 10.0,
                'condition_1' => 502,
                'condition_2' => 502,
            ])),
        ]);

        $factor = $this->weatherService->getWeatherSalesFactor(14.5995, 120.9842, Carbon::today());

        // 25mm total rain = Heavy Rain factor 0.60
        $this->assertEquals(0.60, $factor);
    }

    public function test_extreme_rain_returns_lowest_factor(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse([
                'rain_1' => 30.0,
                'rain_2' => 25.0,
            ])),
        ]);

        $factor = $this->weatherService->getWeatherSalesFactor(14.5995, 120.9842, Carbon::today());

        // 55mm total rain = Extreme Rain factor 0.45
        $this->assertEquals(0.45, $factor);
    }

    public function test_thunderstorm_condition_returns_reduced_factor(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse([
                'condition_1' => 211,
                'condition_2' => 211,
            ])),
        ]);

        $factor = $this->weatherService->getWeatherSalesFactor(14.5995, 120.9842, Carbon::today());

        // Thunderstorm (code 2xx) = factor 0.65
        $this->assertEquals(0.65, $factor);
    }

    public function test_extreme_heat_returns_reduced_factor(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse([
                'temp_1' => 36.0,
                'temp_2' => 39.0,
            ])),
        ]);

        $factor = $this->weatherService->getWeatherSalesFactor(14.5995, 120.9842, Carbon::today());

        // Max temp 39C = Extreme Heat factor 0.80
        $this->assertEquals(0.80, $factor);
    }

    public function test_typhoon_signal_2_returns_severe_factor(): void
    {
        // Typhoon during season (June-November)
        Carbon::setTestNow(Carbon::create(2026, 8, 15));

        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse([
                'wind_kph_1' => 80,
                'wind_kph_2' => 100,
            ])),
        ]);

        $service = new WeatherService;
        $factor = $service->getWeatherSalesFactor(14.5995, 120.9842, Carbon::today());

        // Max wind 100 kph = Signal 2 factor 0.50
        $this->assertEquals(0.50, $factor);

        Carbon::setTestNow();
    }

    public function test_typhoon_signal_1_only_during_season(): void
    {
        // Outside typhoon season (March)
        Carbon::setTestNow(Carbon::create(2026, 3, 15));

        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse([
                'wind_kph_1' => 40,
                'wind_kph_2' => 50,
            ])),
        ]);

        $service = new WeatherService;
        $factor = $service->getWeatherSalesFactor(14.5995, 120.9842, Carbon::today());

        // Signal 1 winds (30-60 kph) outside season should not reduce factor
        $this->assertEquals(1.0, $factor);

        Carbon::setTestNow();
    }

    public function test_worst_factor_wins_when_multiple_conditions(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse([
                'rain_1' => 15.0,
                'rain_2' => 10.0,
                'temp_1' => 36.0,
                'temp_2' => 39.0,
                'condition_1' => 211,
                'condition_2' => 211,
            ])),
        ]);

        $factor = $this->weatherService->getWeatherSalesFactor(14.5995, 120.9842, Carbon::today());

        // Rain 25mm = 0.60, Thunderstorm = 0.65, Extreme Heat = 0.80
        // Worst (minimum) wins: 0.45 (extreme rain > 50mm? No, 25mm = 0.60)
        // Actually: rain = 0.60, thunderstorm = 0.65, heat = 0.80 → min = 0.60
        $this->assertEquals(0.60, $factor);
    }

    public function test_api_failure_returns_null_forecast(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response('Server Error', 500),
        ]);

        $forecast = $this->weatherService->getForecast(14.5995, 120.9842);

        $this->assertNull($forecast);
    }

    public function test_api_failure_returns_neutral_factor(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response('Server Error', 500),
        ]);

        $factor = $this->weatherService->getWeatherSalesFactor(14.5995, 120.9842, Carbon::today());

        $this->assertEquals(1.0, $factor);
    }

    public function test_date_beyond_forecast_window_returns_neutral_factor(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse()),
        ]);

        // Request a date 10 days out (beyond 5-day OWM window)
        $factor = $this->weatherService->getWeatherSalesFactor(14.5995, 120.9842, Carbon::today()->addDays(10));

        $this->assertEquals(1.0, $factor);
    }

    public function test_get_weather_info_returns_full_data(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse([
                'rain_1' => 5.0,
                'rain_2' => 3.0,
            ])),
        ]);

        $info = $this->weatherService->getWeatherInfo(14.5995, 120.9842, Carbon::today());

        $this->assertNotNull($info);
        $this->assertArrayHasKey('condition', $info);
        $this->assertArrayHasKey('max_temp', $info);
        $this->assertArrayHasKey('total_rain', $info);
        $this->assertArrayHasKey('max_wind', $info);
        $this->assertArrayHasKey('sales_factor', $info);
        $this->assertEquals(8.0, $info['total_rain']);
    }

    public function test_get_weather_info_returns_null_when_unavailable(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response('Server Error', 500),
        ]);

        $info = $this->weatherService->getWeatherInfo(14.5995, 120.9842, Carbon::today());

        $this->assertNull($info);
    }

    public function test_drizzle_rain_factor(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse([
                'rain_1' => 0.5,
                'rain_2' => 0.5,
            ])),
        ]);

        $factor = $this->weatherService->getWeatherSalesFactor(14.5995, 120.9842, Carbon::today());

        // 1mm total rain = Drizzle factor 0.90
        $this->assertEquals(0.90, $factor);
    }

    public function test_moderate_rain_factor(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse([
                'rain_1' => 5.0,
                'rain_2' => 5.0,
            ])),
        ]);

        $factor = $this->weatherService->getWeatherSalesFactor(14.5995, 120.9842, Carbon::today());

        // 10mm total rain = Moderate Rain factor 0.75
        $this->assertEquals(0.75, $factor);
    }

    public function test_very_hot_weather_factor(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse([
                'temp_1' => 33.0,
                'temp_2' => 36.0,
            ])),
        ]);

        $factor = $this->weatherService->getWeatherSalesFactor(14.5995, 120.9842, Carbon::today());

        // Max temp 36C = Very Hot factor 0.90
        $this->assertEquals(0.90, $factor);
    }
}
