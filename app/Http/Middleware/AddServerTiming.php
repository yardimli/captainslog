<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddServerTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $response = $next($request);
        $duration = (microtime(true) - $startedAt) * 1000;

        $response->headers->set('Server-Timing', sprintf('laravel;dur=%.1f', $duration), false);

        return $response;
    }
}
