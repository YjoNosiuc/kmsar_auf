<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Spatie\Permission\Middleware\PermissionMiddleware;

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
            if ($request->expectsJson()) {
                return null;
            }

            $accept = strtolower((string) $request->header('Accept', ''));
            $prefersHtml = $accept === ''
                || str_contains($accept, 'text/html')
                || str_contains($accept, 'application/xhtml+xml');

            if (! $prefersHtml && ($request->ajax() || str_contains($accept, 'application/json'))) {
                return null;
            }

            if (! $request->hasSession()) {
                return redirect()->route('login', ['expired' => 1]);
            }

            if ($request->isMethod('post') && $request->is('login')) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login', ['expired' => 1]);
            }

            if ($request->isMethod('post') && $request->is('register')) {
                $request->session()->regenerateToken();

                return redirect()->route('register')
                    ->withInput($request->except('_token', 'password', 'password_confirmation'))
                    ->withErrors([
                        'session' => __('Your session expired. Please review the form and submit again.'),
                    ]);
            }

            if ($request->isMethod('post') && $request->is('verify-email', 'verify-email/*')) {
                $request->session()->regenerateToken();

                return redirect()->route('register.verify-email')
                    ->withErrors([
                        'otp' => __('Your session expired. Please enter the verification code again or resend a new code.'),
                    ]);
            }

            $request->session()->regenerateToken();

            return redirect()->route('login', ['expired' => 1]);
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
