<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && (! $user instanceof User || ! $this->isActive($user->getAuthIdentifier()))) {
            return $this->reject($request);
        }

        if ($request->routeIs('two-factor.login', 'two-factor.login.store')
            && $request->session()->has('login.id')
            && ! $this->isActive($request->session()->get('login.id'))) {
            return $this->reject($request);
        }

        $response = $next($request);
        $user = $request->user();

        if ($user !== null && (! $user instanceof User || ! $this->isActive($user->getAuthIdentifier()))) {
            return $this->reject($request);
        }

        return $response;
    }

    private function isActive(mixed $userId): bool
    {
        return User::query()->whereKey($userId)->where('is_active', true)->exists();
    }

    private function reject(Request $request): Response
    {
        Auth::guard(config('fortify.guard', 'web'))->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() && ! $request->headers->has('X-Inertia')) {
            return response()->json(['message' => __('auth.failed')], Response::HTTP_UNAUTHORIZED);
        }

        return to_route('login')->withErrors(['email' => __('auth.failed')]);
    }
}
