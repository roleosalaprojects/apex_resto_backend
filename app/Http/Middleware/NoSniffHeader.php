<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoSniffHeader
{
    /**
     * Block MIME-sniffing attacks. Without this header, an attacker who
     * convinces the server to host an HTML-ish polyglot image (e.g. a
     * PNG with embedded HTML) can sometimes trick a browser into
     * interpreting the response as HTML and executing it. With nosniff,
     * the browser honours the declared Content-Type strictly.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
