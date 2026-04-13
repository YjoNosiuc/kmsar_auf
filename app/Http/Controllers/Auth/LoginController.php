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
     */
    protected function redirectAfterLogin(User $user): RedirectResponse
    {
        /** @var User $authUser */
        $authUser = auth()->user() ?? $user;

        $url = match (true) {
            $authUser->hasRole('super_admin') => route('admin.dashboard'),
            $authUser->hasAnyRole(['ovpri_admin', 'cdaic_admin']) => route('ovpri.dashboard'),
            $authUser->hasAnyRole(['college_dean', 'unit_head']) => route('dean.dashboard'),
            $authUser->hasAnyRole(['faculty', 'co_author']) => route('research.index'),
            default => '/',
        };

        return redirect()->intended($url);
    }
}
