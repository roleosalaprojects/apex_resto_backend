<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorize an openclaw-authenticated request against a required ability.
 *
 * Pairs with the openclaw guard: auth:openclaw resolves the api_token and
 * sets it on the request attributes; this middleware then checks whether
 * that token's abilities include the one this route declares.
 */
class CheckOpenclawAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        // Self-contained lookup. Auth::viaRequest sets an api_token attribute as
        // a side effect of resolving the user, but the openclaw guard caches the
        // resolved user across requests within a single test method, so the
        // attribute can be stale. Re-resolving from the bearer is cheap and
        // makes this middleware robust on its own.
        $token = ApiToken::findByBearer($request->bearerToken());

        if ($token === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $token->hasAbility($ability)) {
            return response()->json([
                'success' => false,
                'message' => "This token is missing the required ability: {$ability}.",
            ], 403);
        }

        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
