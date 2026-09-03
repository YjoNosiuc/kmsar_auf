<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerificationMail;
use App\Models\College;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class RegisterController extends Controller
{
    public function create(Request $request): View|RedirectResponse|Response
    {
        if (Auth::check()) {
            return redirect()->route('research.index');
        }

        if ($request->boolean('expired') && $request->hasSession()) {
            $request->session()->regenerateToken();
        }

        $colleges = College::orderBy('name')->get();

        return response()
            ->view('auth.register', compact('colleges'))
            ->header('Cache-Control', 'no-cache, private, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'employee_number' => filled($request->input('employee_number'))
                ? $request->input('employee_number')
                : null,
            'institution' => filled($request->input('institution'))
                ? trim((string) $request->input('institution'))
                : null,
        ]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'employee_number' => [
                'nullable',
                'string',
                'regex:/^[0-9]{1,10}$/',
                Rule::unique('users', 'employee_number'),
                Rule::requiredIf(fn () => in_array($request->input('user_type'), ['faculty', 'staff', 'student'], true)),
            ],
            'college_id' => ['required', 'exists:colleges,id'],
            'program_id' => [
                'nullable',
                'exists:programs,id',
            ],
            'user_type' => ['required', 'in:faculty,staff,student,external_affiliate'],
            'institution' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'employee_number.regex' => __('The ID number must be 1 to 10 digits.'),
        ]);

        $validated['email'] = strtolower($validated['email']);

        session()->put('pending_registration', $validated);
        session()->save();

        if (! $this->issueVerificationOtp($validated['email'], $validated['first_name'])) {
            return redirect()->route('register.verify-email')
                ->withErrors(['otp' => __('Failed to send the verification code. Please use Resend code.')]);
        }

        return redirect()->route('register.verify-email');
    }

    public function showVerifyEmail(): View|RedirectResponse
    {
        $pending = session('pending_registration');
        if (! is_array($pending) || empty($pending['email'])) {
            return redirect()->route('register');
        }

        return view('auth.verify-email', [
            'email' => $pending['email'],
        ]);
    }

    public function confirmEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'email' => ['nullable', 'email'],
        ]);

        $pending = session('pending_registration');
        $pendingMessage = __('Your email has been verified. Your account is pending approval by the administrator.');
        $email = is_array($pending) && ! empty($pending['email'])
            ? $pending['email']
            : strtolower((string) $request->input('email', ''));

        if ($email === '') {
            return redirect()->route('register');
        }

        if (! is_array($pending) || empty($pending['email'])) {
            $existing = User::query()->where('email', $email)->first();
            if ($existing?->is_pending) {
                return redirect()->route('login')->with('info', $pendingMessage);
            }

            return redirect()->route('register');
        }

        $record = PasswordResetOtp::query()
            ->where('email', $pending['email'])
            ->where('otp', $request->input('otp'))
            ->latest('id')
            ->first();

        if (! $record || $record->isExpired()) {
            $existing = User::query()->where('email', $pending['email'])->first();
            if ($existing?->is_pending) {
                $this->forgetPendingRegistration($pending['email']);

                return redirect()->route('login')->with('info', $pendingMessage);
            }

            return back()->withErrors(['otp' => __('Invalid or expired verification code.')]);
        }

        $existing = User::query()->where('email', $pending['email'])->first();
        if ($existing) {
            $this->forgetPendingRegistration($pending['email']);

            return redirect()->route('login')->with('info', $pendingMessage);
        }

        $employeeTaken = filled($pending['employee_number'] ?? null)
            && User::query()->where('employee_number', $pending['employee_number'])->exists();

        if ($employeeTaken) {
            $this->forgetPendingRegistration($pending['email']);

            return redirect()->route('register')
                ->withErrors(['employee_number' => __('This ID number is already registered. Please sign in or register with different details.')]);
        }

        DB::transaction(function () use ($pending) {
            $user = User::create([
                'first_name' => $pending['first_name'],
                'last_name' => $pending['last_name'],
                'middle_name' => $pending['middle_name'] ?? null,
                'suffix' => $pending['suffix'] ?? null,
                'name' => trim($pending['first_name'].' '.$pending['last_name']),
                'email' => $pending['email'],
                'password' => $pending['password'],
                'employee_number' => $pending['employee_number'] ?? null,
                'college_id' => $pending['college_id'] ?? null,
                'program_id' => $pending['program_id'] ?? null,
                'institution' => $pending['institution'] ?? null,
                'user_type' => $pending['user_type'],
                'is_active' => false,
                'is_pending' => true,
            ]);
            $user->assignRole('viewer');
        });

        $this->forgetPendingRegistration($pending['email']);

        return redirect()->route('login')->with('info', $pendingMessage);
    }

    public function resendVerification(): RedirectResponse
    {
        $pending = session('pending_registration');
        if (! is_array($pending) || empty($pending['email'])) {
            return redirect()->route('register');
        }

        if (! $this->issueVerificationOtp($pending['email'], $pending['first_name'] ?? $pending['email'])) {
            return back()->withErrors(['otp' => __('Failed to send the verification code. Please try again.')]);
        }

        return back()->with('success', __('Verification code resent.'));
    }

    private function issueVerificationOtp(string $email, string $firstName): bool
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PasswordResetOtp::query()->where('email', $email)->delete();
        PasswordResetOtp::query()->create([
            'email' => $email,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(1),
        ]);

        try {
            Mail::to($email)->send(new EmailVerificationMail($otp, $firstName));

            return true;
        } catch (Throwable $e) {
            Log::warning('Verification email failed: '.$e->getMessage(), [
                'email' => $email,
            ]);

            if (config('app.debug')) {
                Log::info('Registration OTP (local debug — mail unavailable)', [
                    'email' => $email,
                    'otp' => $otp,
                ]);

                session()->flash(
                    'warning',
                    __('Email could not be sent. Your verification code was written to storage/logs/laravel.log (local debug only).'),
                );

                return true;
            }

            return false;
        }
    }

    private function forgetPendingRegistration(string $email): void
    {
        PasswordResetOtp::query()->where('email', $email)->delete();
        session()->forget('pending_registration');
    }
}
