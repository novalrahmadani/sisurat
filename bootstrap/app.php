<?php

use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\UseBuiltAssetsForNetworkRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        if (getenv('SISURAT_HTTPS_TUNNEL') === '1') {
            $middleware->trustProxies(at: ['127.0.0.1', '::1'], headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
        }
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(prepend: [UseBuiltAssetsForNetworkRequests::class], append: [
            EnsureActiveAccount::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->appendToPriorityList(StartSession::class, EnsureActiveAccount::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['pin', 'pin_confirmation']);
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($response->getStatusCode() !== 419
                || ($request->expectsJson() && ! $request->headers->has('X-Inertia'))) {
                return $response;
            }

            if ($request->isMethod('POST') && $request->routeIs('internship.*')) {
                $notice = $request->routeIs('internship.verify-pin')
                    ? 'Sesi formulir telah diperbarui. Masukkan PIN Anda kembali. PIN tetap berlaku.'
                    : 'Sesi formulir telah diperbarui. Silakan periksa dan kirim ulang formulir. Pengiriman sebelumnya belum diproses.';

                $redirect = $request->routeIs('internship.recover')
                    ? to_route('internship.recovery', ['token' => $request->route('token')], status: 303)
                    : to_route('internship.show', status: 303);

                return $redirect->with('internship_session_notice', $notice);
            }

            if (! $request->routeIs('login.store')) {
                return $response;
            }

            return to_route('login', status: 303)->with(
                'status',
                'Halaman masuk sudah kedaluwarsa. Silakan masukkan kembali email dan kata sandi.',
            );
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
