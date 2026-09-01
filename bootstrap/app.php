<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Exceptions\Renderer\Renderer;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'bayarcash/callback',
            'bayarcash/direct-debit/callback',
            'doku/callback',
            'doku/redirect',
            '__deploy/*',
        ]);

        $middleware->alias([
            'role'       => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'profile_complete' => \App\Http\Middleware\EnsureProfileIsComplete::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Dalam production, hanya Superadmin yang sedang login dapat melihat page debug
        // penuh (stack trace). User lain dan admin biasa sentiasa mendapat halaman error
        // mesra pengguna di resources/views/errors/*, tanpa mendedahkan stack Laravel.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            $isSuperadmin = $request->user()?->hasRole('Superadmin') ?? false;
            $isClientError = $e instanceof HttpExceptionInterface && $e->getStatusCode() < 500;

            if (app()->environment('production') && $isSuperadmin && ! $isClientError) {
                try {
                    return response(
                        app(Renderer::class)->render($request, $e),
                        $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500,
                        $e instanceof HttpExceptionInterface ? $e->getHeaders() : []
                    );
                } catch (Throwable) {
                    return null;
                }
            }

            return null;
        });
    })->create();
