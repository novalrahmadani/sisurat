<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseBuiltAssetsForNetworkRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower(trim($request->getHost(), '[]'));
        $isLoopback = $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || $host === '::1'
            || (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && str_starts_with($host, '127.'));

        if (! app()->environment('local') || $isLoopback) {
            return $next($request);
        }

        $vite = app(Vite::class);
        if (! $vite->isRunningHot()) {
            return $next($request);
        }

        $hotFile = $vite->hotFile();
        // A phone cannot reach the laptop's loopback Vite server. Serve its built assets from PHP instead.
        $vite->useHotFile('');

        try {
            return $next($request);
        } finally {
            $vite->useHotFile($hotFile);
        }
    }
}
