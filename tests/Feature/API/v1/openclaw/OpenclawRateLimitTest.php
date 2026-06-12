<?php

namespace Tests\Feature\API\v1\openclaw;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OpenclawRateLimitTest extends TestCase
{
    public function test_throttle_openclaw_middleware_is_attached_to_each_endpoint(): void
    {
        $openclawRoutes = collect(Route::getRoutes())
            ->filter(fn ($r) => str_starts_with((string) $r->uri(), 'api/v1/openclaw/'));

        $this->assertNotEmpty($openclawRoutes, 'No openclaw routes are registered.');

        foreach ($openclawRoutes as $r) {
            $this->assertContains(
                'throttle:openclaw',
                $r->middleware(),
                "Route {$r->uri()} is missing throttle:openclaw middleware."
            );
            $this->assertContains(
                'auth:openclaw',
                $r->middleware(),
                "Route {$r->uri()} is missing auth:openclaw middleware."
            );
        }
    }

    public function test_openclaw_rate_limiter_is_registered(): void
    {
        $callback = RateLimiter::limiter('openclaw');
        $this->assertNotNull($callback, 'Expected the openclaw rate limiter to be registered.');

        $request = \Illuminate\Http\Request::create('/api/v1/openclaw/snapshot');
        $limit = $callback($request);

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame((int) config('openclaw.rate_limit_per_minute', 120), $limit->maxAttempts);
        $this->assertSame(60, $limit->decaySeconds);
    }
}
