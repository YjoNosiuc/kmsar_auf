<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Show the login form (local bcrypt; LDAP integration planned).
     */
    public function create(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectAfterLogin(Auth::user());
        }

        return view('auth.login');
    }

    /**
     * Authenticate via employee number or email + password.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $login = $validated['login'];
        $password = $validated['password'];
        $field = str_contains($login, '@') ? 'email' : 'employee_number';

        if (! Auth::attempt([$field => $login, 'password' => $password], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'login' => [__('These credentials do not match our records.')],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->is_pending) {
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => [__('Your account is pending approval by the administrator. Please wait for confirmation.')],
            ]);
        }

        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => [__('This account is inactive.')],
            ]);
        }

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->redirectAfterLogin($user);
    }

    /**
     * Log the user out of the application.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * First matching Spatie role wins (highest-privilege roles checked first).
     *
     * Do not blindly follow url.intended — a stale tab or expired session often
     * stores a route the user cannot access, which looks like a random 403.
     */
    protected function redirectAfterLogin(User $user): RedirectResponse
    {
        /** @var User $authUser */
        $authUser = auth()->user() ?? $user;
        $home = $this->homeUrl($authUser);
        $intended = session()->pull('url.intended');

        if (is_string($intended) && $intended !== '' && $this->intendedIsAllowed($authUser, $intended)) {
            return redirect()->to($intended);
        }

        return redirect()->to($home);
    }

    protected function homeUrl(User $user): string
    {
        return match (true) {
            $user->hasRole('super_admin') => route('admin.dashboard'),
            $user->hasAnyRole(['ovpri_admin', 'cdaic_admin']) => route('ovpri.dashboard'),
            $user->hasAnyRole(['college_dean', 'unit_head']) => route('dean.dashboard'),
            $user->hasAnyRole(['faculty', 'viewer']) => route('research.index'),
            default => '/',
        };
    }

    protected function intendedIsAllowed(User $user, string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $host = $parts['host'] ?? null;
        if (is_string($host) && is_string($appHost) && strcasecmp($host, $appHost) !== 0) {
            return false;
        }

        $path = '/'.ltrim((string) ($parts['path'] ?? '/'), '/');

        if ($user->hasRole('super_admin')) {
            return true;
        }

        $prefixes = match (true) {
            $user->hasAnyRole(['ovpri_admin', 'cdaic_admin']) => ['/ovpri', '/reports', '/profile', '/notifications'],
            $user->hasAnyRole(['college_dean', 'unit_head']) => ['/dean', '/approval', '/reports', '/profile', '/notifications'],
            $user->hasAnyRole(['faculty', 'viewer']) => ['/research', '/profile', '/notifications', '/documents'],
            default => [],
        };

        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
