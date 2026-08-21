<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    public function showForgotForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return back()
                ->withErrors(['email' => __('We could not find an account with that email address.')])
                ->withInput($request->only('email'));
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PasswordResetOtp::query()->where('email', $email)->delete();

        PasswordResetOtp::query()->create([
            'email' => $email,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(1),
        ]);

        // OTP must be sent synchronously — user is waiting
        try {
            Mail::to($email)->send(new PasswordResetOtpMail($otp, $user->name ?? $email));
        } catch (Throwable $e) {
            Log::warning('OTP email failed: '.$e->getMessage());

            return back()->withErrors([
                'email' => __('Failed to send verification code. Please try again.'),
            ])->withInput($request->only('email'));
        }

        return redirect()
            ->route('password.verify', ['email' => $email])
            ->with('status', __('A 6-digit verification code has been sent to your email.'));
    }

    public function showVerifyForm(Request $request): View|RedirectResponse
    {
        $email = strtolower(trim((string) $request->query('email', old('email', ''))));

        if ($email === '') {
            return redirect()->route('password.request');
        }

        $record = PasswordResetOtp::query()
            ->where('email', $email)
            ->latest('id')
            ->first();

        $expiresAt = $record?->expires_at;

        return view('auth.verify-otp', [
            'email' => $email,
            'expiresAt' => $expiresAt,
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $email = strtolower(trim($validated['email']));
        $otp = $validated['otp'];

        $record = PasswordResetOtp::query()
            ->where('email', $email)
            ->where('otp', $otp)
            ->latest('id')
            ->first();

        if (! $record || $record->isExpired()) {
            return back()
                ->withErrors(['otp' => __('Invalid or expired code')])
                ->withInput($request->only('email'));
        }

        return redirect()->route('password.reset', [
            'email' => $email,
            'otp' => $otp,
        ]);
    }

    public function showResetForm(Request $request): View|RedirectResponse
    {
        $email = strtolower(trim((string) $request->query('email', old('email', ''))));
        $otp = (string) $request->query('otp', old('otp', ''));

        if ($email === '' || $otp === '') {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password', [
            'email' => $email,
            'otp' => $otp,
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $email = strtolower(trim($validated['email']));
        $otp = $validated['otp'];

        $record = PasswordResetOtp::query()
            ->where('email', $email)
            ->where('otp', $otp)
            ->latest('id')
            ->first();

        if (! $record || $record->isExpired()) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => __('Invalid or expired code')]);
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => __('We could not find an account with that email address.')]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        PasswordResetOtp::query()->where('email', $email)->delete();

        return redirect()
            ->route('login')
            ->with('status', __('Password reset successfully. Please login.'));
    }
}
