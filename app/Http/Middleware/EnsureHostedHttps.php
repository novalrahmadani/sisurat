<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureHostedHttps
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = rtrim(strtolower($request->getHost()), '.');
        if (! in_array($host, config('app.https_hosts', []), true) || $request->isSecure()) {
            return $next($request);
        }

        $target = 'https://'.$host.$request->getRequestUri();
        if ($request->header('X-Inertia')) {
            if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
                // A full-page visit cannot replay a POST-only action. Reopen its form.
                $path = $request->is('absensi-magang', 'absensi-magang/*') ? '/absensi-magang' : '/';
                $target = 'https://'.$host.$path;
            }
            $response = Inertia::location($target);
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        }

        return redirect()->away($target, 308);
    }
}
