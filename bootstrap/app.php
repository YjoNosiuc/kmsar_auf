<?php

use App\Http\Middleware\RoleMiddleware;
use App\Support\FriendlyExceptionResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'nocache' => \App\Http\Middleware\NoCacheHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            return FriendlyExceptionResponse::tokenMismatch($request);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return FriendlyExceptionResponse::forbidden($request, $e->getMessage());
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() === 403) {
                return FriendlyExceptionResponse::forbidden($request, $e->getMessage());
            }

            if ($e->getStatusCode() === 419) {
                return FriendlyExceptionResponse::tokenMismatch($request);
            }

            return null;
        });

        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $maxMb = (int) (config('kmsar.max_upload_size_kb', 102400) / 1024);
            $message = __('The uploaded file exceeds the maximum allowed size of :size MB.', ['size' => $maxMb]);

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return back()
                ->withInput()
                ->withErrors(['files' => $message]);
        });
    })->create();
