<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Converts CSRF (419) and authorization (403) failures into friendly redirects
 * instead of standalone error pages during normal browser use.
 */
class FriendlyExceptionResponse
{
    public static function wantsHtml(Request $request): bool
    {
        if ($request->expectsJson()) {
            return false;
        }

        $accept = strtolower((string) $request->header('Accept', ''));

        if ($request->ajax() || str_contains($accept, 'application/json')) {
            return false;
        }

        return $accept === ''
            || str_contains($accept, 'text/html')
            || str_contains($accept, 'application/xhtml+xml');
    }

    public static function tokenMismatch(Request $request): Response
    {
        if (! self::wantsHtml($request)) {
            return response()->json([
                'message' => __('Your session expired. Please refresh the page and try again.'),
            ], 419);
        }

        if (! $request->hasSession()) {
            return redirect()->route('login', ['expired' => 1]);
        }

        if ($request->isMethod('POST') && $request->is('login')) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login', ['expired' => 1]);
        }

        if ($request->isMethod('POST') && $request->is('register')) {
            $request->session()->regenerateToken();

            return redirect()->route('register')
                ->withInput($request->except('_token', 'password', 'password_confirmation'))
                ->withErrors([
                    'session' => __('Your session expired. Please review the form and submit again.'),
                ]);
        }

        if ($request->isMethod('POST') && $request->is('verify-email', 'verify-email/*')) {
            $request->session()->regenerateToken();

            return redirect()->route('register.verify-email')
                ->withErrors([
                    'otp' => __('Your session expired. Please enter the verification code again or resend a new code.'),
                ]);
        }

        $request->session()->regenerateToken();

        $user = $request->user();
        if ($user instanceof User) {
            return self::safeBack(
                $request,
                $user,
                __('Your session expired. Please try again.')
            );
        }

        return redirect()->route('login', ['expired' => 1]);
    }

    public static function forbidden(Request $request, ?string $message = null): Response
    {
        if (! self::wantsHtml($request)) {
            return response()->json([
                'message' => self::normalizeForbiddenMessage($message),
            ], 403);
        }

        $friendly = self::normalizeForbiddenMessage($message);
        $user = $request->user();

        if ($user instanceof User) {
            if ($request->isMethod('POST', 'PUT', 'PATCH', 'DELETE')) {
                return self::safeBack($request, $user, $friendly);
            }

            return redirect()->to(self::homeUrlFor($user))->with('error', $friendly);
        }

        return redirect()->route('login')->with('info', __('Please sign in to continue.'));
    }

    public static function homeUrlFor(User $user): string
    {
        return match (true) {
            $user->hasRole('super_admin') => route('admin.dashboard'),
            $user->hasAnyRole(['ovpri_admin', 'cdaic_admin']) => route('ovpri.dashboard'),
            $user->hasAnyRole(['college_dean', 'unit_head']) => route('dean.dashboard'),
            $user->hasAnyRole(['faculty', 'viewer']) => route('research.index'),
            default => url('/'),
        };
    }

    public static function safeBack(Request $request, User $user, string $message): RedirectResponse
    {
        $previous = url()->previous();
        if (filled($previous) && $previous !== $request->fullUrl()) {
            return back()->with('error', $message);
        }

        return redirect()->to(self::homeUrlFor($user))->with('error', $message);
    }

    protected static function normalizeForbiddenMessage(?string $message): string
    {
        if (! filled($message) || in_array($message, ['Forbidden', 'This action is unauthorized.'], true)) {
            return __('You do not have permission to perform this action.');
        }

        return $message;
    }
}
