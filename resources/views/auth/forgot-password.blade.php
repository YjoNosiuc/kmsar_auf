@extends('layouts.auth')

@section('title', 'Forgot password — ' . config('app.name', 'KMSAR'))

@push('styles')
<style>
    .kmsar-auth-shell { margin: 0; min-height: 100vh; font-family: var(--font-sans, 'Inter', system-ui, sans-serif); background: var(--color-surface, #F8FAFC); }
    .kmsar-login { display: flex; min-height: 100vh; flex-direction: column; }
    @media (min-width: 768px) { .kmsar-login { flex-direction: row; } }
    .kmsar-login-brand { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 2.5rem 2rem; background-color: #1E3A8A; color: #fff; position: relative; overflow: hidden; }
    .kmsar-login-brand::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse 80% 60% at 20% 20%, rgba(212, 175, 55, 0.12), transparent 55%); pointer-events: none; }
    .kmsar-login-brand-inner { position: relative; max-width: 28rem; margin: 0 auto; width: 100%; }
    .kmsar-login-brand-inst { font-size: 0.8125rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: rgba(255, 255, 255, 0.85); margin-bottom: 0.5rem; }
    .kmsar-login-brand-title { font-size: clamp(1.75rem, 4vw, 2.25rem); font-weight: 700; line-height: 1.15; margin-bottom: 0.75rem; }
    .kmsar-login-brand-title span { color: #D4AF37; }
    .kmsar-login-brand-sub { font-size: 0.9375rem; line-height: 1.55; color: rgba(255, 255, 255, 0.88); }
    .kmsar-login-panel { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem 1.5rem 3rem; background: var(--color-card, #fff); }
    @media (min-width: 768px) { .kmsar-login-panel { box-shadow: -12px 0 40px rgba(15, 23, 42, 0.06); } }
    .kmsar-login-card { width: 100%; max-width: 22rem; }
    .kmsar-login-heading { font-size: 1.375rem; font-weight: 700; color: var(--color-text-primary, #0F172A); margin-bottom: 0.25rem; }
    .kmsar-login-lead { font-size: 0.875rem; color: var(--color-text-secondary, #475569); margin-bottom: 1.5rem; }
</style>
@endpush

@section('content')
<div class="kmsar-login">
    <div class="kmsar-login-brand">
        <div class="kmsar-login-brand-inner">
            <div class="kmsar-login-brand-inst">Angeles University Foundation</div>
            <div class="kmsar-login-brand-title">KMSAR</div>
            <p class="kmsar-login-brand-sub">We will send a 6-digit verification code to your email so you can reset your password securely.</p>
        </div>
    </div>

    <div class="kmsar-login-panel">
        <div class="kmsar-login-card">
            <h1 class="kmsar-login-heading">Forgot password</h1>
            <p class="kmsar-login-lead">Enter the email address linked to your KMSAR account.</p>

            @if (session('status'))
                <x-alert type="success" class="kmsar-form-group" :message="session('status')" />
            @endif

            @if ($errors->any())
                <x-alert type="danger" class="kmsar-form-group">
                    <ul style="margin:0;padding-left:1.1rem;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="kmsar-form-group">
                    <label class="kmsar-form-label" for="email">Email address</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="kmsar-input"
                        autocomplete="email"
                        required
                        autofocus
                        placeholder="name@yopmail.com"
                    >
                </div>

                <div class="kmsar-form-group" style="margin-top: 1.25rem;">
                    <button type="submit" class="kmsar-btn kmsar-btn--primary kmsar-btn--lg" style="width: 100%;">
                        Send verification code
                    </button>
                </div>

                <p class="kmsar-login-lead" style="margin-top: 1.25rem; margin-bottom: 0; text-align: center;">
                    <a href="{{ route('login') }}" style="color: #1E3A8A; font-weight: 600; text-decoration: underline;">Back to sign in</a>
                </p>
            </form>

            <p style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--color-border, #E2E8F0); font-size: 0.8125rem; color: var(--color-text-muted, #94A3B8); line-height: 1.5;">
                If you do not receive an email, check your spam folder or contact your system administrator.
            </p>
        </div>
    </div>
</div>
@endsection
