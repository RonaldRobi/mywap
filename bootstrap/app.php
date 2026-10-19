<?php

use App\Http\Middleware\EnsureApiAdminAccess;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Exceptions\Renderer\Renderer;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
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
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'bayarcash/callback',
            'bayarcash/direct-debit/callback',
            'doku/callback',
            'doku/redirect',
            '__deploy/*',
            '__backup/*',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'profile_complete' => EnsureProfileIsComplete::class,
            'api_admin' => EnsureApiAdminAccess::class,
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
