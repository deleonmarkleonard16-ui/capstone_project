<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PrivateGuidanceResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        // Preserve stricter cache directives applied by authenticated routes.
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}
