<?php

namespace App\Http\Middleware;

use App\Models\Ecommerce\ShopVisit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogShopVisit
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get or create visitor ID (persistent across sessions)
        $visitorId = $request->cookie('shop_visitor_id');
        if (! $visitorId) {
            $visitorId = Str::uuid()->toString();
            Cookie::queue('shop_visitor_id', $visitorId, 60 * 24 * 365); // 1 year
        }

        // Store visitor ID for use in response
        $request->attributes->set('visitor_id', $visitorId);

        // Log the visit
        $this->logVisit($request, $visitorId);

        return $next($request);
    }

    protected function logVisit(Request $request, string $visitorId): void
    {
        $userAgent = $request->userAgent();
        $deviceInfo = $this->parseUserAgent($userAgent);
        $referrer = $request->header('referer');
        $utmParams = $this->extractUtmParams($request);

        // Determine page type and related IDs
        $pageInfo = $this->determinePageInfo($request);

        ShopVisit::create([
            'session_id' => session()->getId(),
            'visitor_id' => $visitorId,
            'customer_id' => $this->getCustomerId(),
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
            'page_visited' => $request->path(),
            'page_type' => $pageInfo['type'],
            'referrer' => $referrer,
            'referrer_domain' => $referrer ? parse_url($referrer, PHP_URL_HOST) : null,
            'utm_source' => $utmParams['source'],
            'utm_medium' => $utmParams['medium'],
            'utm_campaign' => $utmParams['campaign'],
            'device_type' => $deviceInfo['device'],
            'browser' => $deviceInfo['browser'],
            'browser_version' => $deviceInfo['browser_version'],
            'platform' => $deviceInfo['platform'],
            'product_id' => $pageInfo['product_id'],
            'category_id' => $pageInfo['category_id'],
            'action' => $pageInfo['action'],
            'action_data' => $pageInfo['action_data'],
            'entered_at' => now(),
        ]);
    }

    protected function getCustomerId(): ?int
    {
        if (auth('customer')->check()) {
            return auth('customer')->id();
        }

        return null;
    }

    protected function parseUserAgent(?string $userAgent): array
    {
        $result = [
            'device' => 'desktop',
            'browser' => null,
            'browser_version' => null,
            'platform' => null,
        ];

        if (! $userAgent) {
            return $result;
        }

        // Device detection
        if (preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent)) {
            $result['device'] = preg_match('/iPad|Tablet/i', $userAgent) ? 'tablet' : 'mobile';
        }

        // Browser detection
        if (preg_match('/Chrome\/([0-9.]+)/i', $userAgent, $matches)) {
            $result['browser'] = 'Chrome';
            $result['browser_version'] = $matches[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/i', $userAgent, $matches)) {
            $result['browser'] = 'Firefox';
            $result['browser_version'] = $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/i', $userAgent, $matches) && ! preg_match('/Chrome/i', $userAgent)) {
            $result['browser'] = 'Safari';
            $result['browser_version'] = $matches[1];
        } elseif (preg_match('/Edge\/([0-9.]+)/i', $userAgent, $matches)) {
            $result['browser'] = 'Edge';
            $result['browser_version'] = $matches[1];
        }

        // Platform detection
        if (preg_match('/Windows/i', $userAgent)) {
            $result['platform'] = 'Windows';
        } elseif (preg_match('/Macintosh|Mac OS/i', $userAgent)) {
            $result['platform'] = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $result['platform'] = 'Linux';
        } elseif (preg_match('/iPhone|iPad/i', $userAgent)) {
            $result['platform'] = 'iOS';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $result['platform'] = 'Android';
        }

        return $result;
    }

    protected function extractUtmParams(Request $request): array
    {
        return [
            'source' => $request->query('utm_source'),
            'medium' => $request->query('utm_medium'),
            'campaign' => $request->query('utm_campaign'),
        ];
    }

    protected function determinePageInfo(Request $request): array
    {
        $path = $request->path();
        $result = [
            'type' => 'browse',
            'product_id' => null,
            'category_id' => null,
            'action' => 'view',
            'action_data' => null,
        ];

        // Product page: shop/products/{id}
        if (preg_match('/shop\/products\/(\d+)/', $path, $matches)) {
            $result['type'] = 'product';
            $result['product_id'] = (int) $matches[1];
        }
        // Cart page
        elseif (str_contains($path, 'cart')) {
            $result['type'] = 'cart';
        }
        // Checkout
        elseif (str_contains($path, 'checkout')) {
            $result['type'] = 'checkout';
        }

        // Check for category filter
        if ($request->has('category')) {
            $result['category_id'] = (int) $request->query('category');
        }

        // Check for search
        if ($request->has('search') || $request->has('q')) {
            $result['action'] = 'search';
            $result['action_data'] = [
                'query' => $request->query('search') ?? $request->query('q'),
            ];
        }

        return $result;
    }
}
