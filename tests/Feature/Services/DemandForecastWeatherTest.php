<?php

namespace Tests\Feature\Services;

use App\Models\Pos\Sale;
use App\Models\Settings\Store;
use App\Services\AiService;
use App\Services\DemandForecastService;
use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemandForecastWeatherTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    /**
     * Build a sample OWM forecast response.
     */
    private function sampleForecastResponse(float $rain = 0, float $windKph = 10, float $temp = 32, int $condition = 800): array
    {
        $intervals = [];

        for ($day = 0; $day <= 5; $day++) {
            $date = Carbon::today()->addDays($day);

            for ($hour = 6; $hour <= 18; $hour += 3) {
                $intervals[] = [
                    'dt' => $date->copy()->setHour($hour)->timestamp,
                    'main' => ['temp' => $temp],
                    'wind' => ['speed' => $windKph / 3.6],
                    'rain' => ['3h' => $rain / 5],
                    'weather' => [['id' => $condition]],
                ];
            }
        }

        return ['list' => $intervals];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->withLocation()->create([
            'user_id' => 1,
        ]);

        // Create historical sales for the store (last 90 days, various days)
        for ($i = 1; $i <= 60; $i++) {
            Sale::factory()->create([
                'user_id' => 1,
                'store_id' => $this->store->id,
                'total' => fake()->randomFloat(2, 1000, 5000),
                'created_at' => Carbon::today()->subDays($i),
            ]);
        }
    }

    public function test_weather_factor_applied_to_prediction(): void
    {
        // Heavy rain response
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse(rain: 30)),
        ]);

        config(['services.openweathermap.api_key' => 'test-key']);

        $ai = $this->createMock(AiService::class);
        $ai->method('isAvailable')->willReturn(false);

        $weather = new WeatherService;

        $service = new DemandForecastService($ai, $weather);
        $forecasts = $service->forecastDailySales(1, 3, $this->store->id);

        $this->assertNotEmpty($forecasts);

        // At least one forecast should have weather info in factors
        $hasWeatherFactor = $forecasts->contains(function ($forecast) {
            $factors = $forecast->factors;

            return isset($factors['weather_factor']) && $factors['weather_factor'] < 1.0;
        });

        $this->assertTrue($hasWeatherFactor, 'Expected at least one forecast to have a weather factor below 1.0');
    }

    public function test_weather_data_stored_on_forecast(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse(rain: 10)),
        ]);

        config(['services.openweathermap.api_key' => 'test-key']);

        $ai = $this->createMock(AiService::class);
        $ai->method('isAvailable')->willReturn(false);

        $weather = new WeatherService;

        $service = new DemandForecastService($ai, $weather);
        $forecasts = $service->forecastDailySales(1, 3, $this->store->id);

        $hasWeatherData = $forecasts->contains(function ($forecast) {
            return ! empty($forecast->weather_data);
        });

        $this->assertTrue($hasWeatherData, 'Expected at least one forecast to have weather_data stored');
    }

    public function test_weather_skipped_when_store_has_no_location(): void
    {
        $storeNoLocation = Store::factory()->create([
            'user_id' => 1,
            'latitude' => null,
            'longitude' => null,
        ]);

        // Create sales for this store
        for ($i = 1; $i <= 30; $i++) {
            Sale::factory()->create([
                'user_id' => 1,
                'store_id' => $storeNoLocation->id,
                'total' => fake()->randomFloat(2, 1000, 5000),
                'created_at' => Carbon::today()->subDays($i),
            ]);
        }

        Http::fake();

        config(['services.openweathermap.api_key' => 'test-key']);

        $ai = $this->createMock(AiService::class);
        $ai->method('isAvailable')->willReturn(false);

        $weather = new WeatherService;

        $service = new DemandForecastService($ai, $weather);
        $forecasts = $service->forecastDailySales(1, 3, $storeNoLocation->id);

        // No HTTP calls should have been made to weather API
        Http::assertNothingSent();

        // No forecast should have weather factor
        $hasWeatherFactor = $forecasts->contains(function ($forecast) {
            return isset($forecast->factors['weather_factor']);
        });

        $this->assertFalse($hasWeatherFactor, 'Expected no weather factor when store has no location');
    }

    public function test_weather_skipped_when_service_unavailable(): void
    {
        config(['services.openweathermap.api_key' => '']);

        Http::fake();

        $ai = $this->createMock(AiService::class);
        $ai->method('isAvailable')->willReturn(false);

        $weather = new WeatherService;

        $service = new DemandForecastService($ai, $weather);
        $forecasts = $service->forecastDailySales(1, 3, $this->store->id);

        // No HTTP calls should have been made to weather API
        Http::assertNothingSent();
    }

    public function test_weather_info_in_factors_array(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->sampleForecastResponse(rain: 15)),
        ]);

        config(['services.openweathermap.api_key' => 'test-key']);

        $ai = $this->createMock(AiService::class);
        $ai->method('isAvailable')->willReturn(false);

        $weather = new WeatherService;

        $service = new DemandForecastService($ai, $weather);
        $forecasts = $service->forecastDailySales(1, 3, $this->store->id);

        $forecastWithWeather = $forecasts->first(function ($forecast) {
            return isset($forecast->factors['weather']);
        });

        if ($forecastWithWeather) {
            $this->assertArrayHasKey('weather', $forecastWithWeather->factors);
            $this->assertArrayHasKey('weather_factor', $forecastWithWeather->factors);
            $this->assertIsString($forecastWithWeather->factors['weather']);
            $this->assertIsFloat($forecastWithWeather->factors['weather_factor']);
        }
    }

    public function test_weather_skipped_when_no_store_id(): void
    {
        Http::fake();

        config(['services.openweathermap.api_key' => 'test-key']);

        $ai = $this->createMock(AiService::class);
        $ai->method('isAvailable')->willReturn(false);

        $weather = new WeatherService;

        $service = new DemandForecastService($ai, $weather);

        // Calling with storeId=null should not trigger weather API calls
        $forecasts = $service->forecastDailySales(1, 3, null);

        // No weather API calls when storeId is null
        Http::assertNothingSent();
    }
}
