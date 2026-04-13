@extends('layouts.auth')

@section('title', 'Create account — ' . config('app.name', 'KMSAR'))

@push('styles')
<style>
    .kmsar-auth-shell {
        margin: 0;
        min-height: 100vh;
        font-family: var(--font-sans, 'Inter', system-ui, sans-serif);
        background: var(--color-surface, #F8FAFC);
    }
    .kmsar-login {
        display: flex;
        min-height: 100vh;
        flex-direction: column;
    }
    @media (min-width: 768px) {
        .kmsar-login {
            flex-direction: row;
        }
    }
    .kmsar-login-brand {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 2.5rem 2rem;
        background-color: #1E3A8A;
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    .kmsar-login-brand::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(ellipse 80% 60% at 20% 20%, rgba(212, 175, 55, 0.12), transparent 55%);
        pointer-events: none;
    }
    .kmsar-login-brand-inner {
        position: relative;
        max-width: 28rem;
        margin: 0 auto;
        width: 100%;
    }
    .kmsar-login-brand-inst {
        font-size: 0.8125rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.85);
        margin-bottom: 0.5rem;
    }
    .kmsar-login-brand-title {
        font-size: clamp(1.75rem, 4vw, 2.25rem);
        font-weight: 700;
        line-height: 1.15;
        margin-bottom: 0.75rem;
    }
    .kmsar-login-brand-title span {
        color: #D4AF37;
    }
    .kmsar-login-brand-sub {
        font-size: 0.9375rem;
        line-height: 1.55;
        color: rgba(255, 255, 255, 0.88);
    }
    .kmsar-login-panel {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1.5rem 3rem;
        background: var(--color-card, #fff);
    }
    @media (min-width: 768px) {
        .kmsar-login-panel {
            box-shadow: -12px 0 40px rgba(15, 23, 42, 0.06);
        }
    }
    .kmsar-login-card {
        width: 100%;
        max-width: 22rem;
    }
    .kmsar-login-heading {
        font-size: 1.375rem;
        font-weight: 700;
        color: var(--color-text-primary, #0F172A);
        margin-bottom: 0.25rem;
    }
    .kmsar-login-lead {
        font-size: 0.875rem;
        color: var(--color-text-secondary, #475569);
        margin-bottom: 1.5rem;
    }
    .kmsar-login-ldap {
        margin-top: 1.5rem;
        padding-top: 1.25rem;
        border-top: 1px solid var(--color-border, #E2E8F0);
        font-size: 0.75rem;
        line-height: 1.5;
        color: var(--color-text-muted, #94A3B8);
    }
</style>
@endpush

@section('content')
<div class="kmsar-login">
    <aside class="kmsar-login-brand" aria-label="Angeles University Foundation">
        <div class="kmsar-login-brand-inner">
            <p class="kmsar-login-brand-inst">Angeles University Foundation</p>
            <h1 class="kmsar-login-brand-title">Knowledge Management System for <span>Academic Research</span></h1>
            <p class="kmsar-login-brand-sub">
                Sign in with your AUF employee credentials to submit research, track approvals, and manage documents.
            </p>
        </div>
    </aside>

    <div class="kmsar-login-panel">
        <div class="kmsar-login-card">
            <h2 class="kmsar-login-heading">Create account</h2>
            <p class="kmsar-login-lead">Register with your AUF employee details to access KMSAR.</p>

            @if ($errors->any())
                <x-alert type="danger" class="kmsar-form-group">
                    <ul style="margin: 0; padding-left: 1.125rem; font-size: var(--text-sm); line-height: 1.5;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            <form method="POST" action="{{ route('register.store') }}">
                @csrf

                <div class="kmsar-form-row-3">
                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label" for="first_name">First Name <span class="kmsar-form-required">*</span></label>
                        <input
                            id="first_name"
                            type="text"
                            name="first_name"
                            value="{{ old('first_name') }}"
                            class="kmsar-input"
                            style="text-transform:uppercase"
                            required
                            autofocus
                            autocomplete="given-name"
                        >
                    </div>
                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label" for="last_name">Last Name <span class="kmsar-form-required">*</span></label>
                        <input
                            id="last_name"
                            type="text"
                            name="last_name"
                            value="{{ old('last_name') }}"
                            class="kmsar-input"
                            style="text-transform:uppercase"
                            required
                            autocomplete="family-name"
                        >
                    </div>
                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label" for="middle_name">Middle Name</label>
                        <input
                            id="middle_name"
                            type="text"
                            name="middle_name"
                            value="{{ old('middle_name') }}"
                            class="kmsar-input"
                            style="text-transform:uppercase"
                            autocomplete="additional-name"
                        >
                    </div>
                </div>

                <div class="kmsar-form-row-2">
                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label" for="suffix">Suffix</label>
                        <input
                            id="suffix"
                            type="text"
                            name="suffix"
                            value="{{ old('suffix') }}"
                            class="kmsar-input"
                            placeholder="Jr., Sr., III"
                            style="text-transform:none"
                            autocomplete="honorific-suffix"
                        >
                    </div>
                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label" for="employee_number">Employee Number <span class="kmsar-form-required">*</span></label>
                        <input
                            id="employee_number"
                            type="text"
                            name="employee_number"
                            value="{{ old('employee_number') }}"
                            class="kmsar-input"
                            style="text-transform:uppercase"
                            placeholder="e.g. AUF-2024-0001"
                            required
                            autocomplete="off"
                        >
                    </div>
                </div>

                <div class="kmsar-form-group">
                    <label class="kmsar-form-label" for="college_id">College / Unit <span class="kmsar-form-required">*</span></label>
                    <select id="college_id" name="college_id" class="kmsar-select" required>
                        <option value="" disabled {{ old('college_id') ? '' : 'selected' }}>— Select college —</option>
                        @foreach ($colleges as $college)
                            <option value="{{ $college->id }}" @selected(old('college_id') == $college->id)>
                                {{ $college->code }} — {{ $college->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="kmsar-form-group">
                    <label class="kmsar-form-label" for="email">Email Address <span class="kmsar-form-required">*</span></label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="kmsar-input"
                        placeholder="your@email.com"
                        required
                        autocomplete="email"
                    >
                </div>

                <div class="kmsar-form-row-2">
                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label" for="password">Password <span class="kmsar-form-required">*</span></label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            class="kmsar-input"
                            placeholder="Min. 8 characters"
                            required
                            autocomplete="new-password"
                        >
                    </div>
                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label" for="password_confirmation">Confirm Password <span class="kmsar-form-required">*</span></label>
                        <input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            class="kmsar-input"
                            placeholder="Repeat password"
                            required
                            autocomplete="new-password"
                        >
                    </div>
                </div>

                <div class="kmsar-form-group" style="margin-top: 1.25rem;">
                    <button type="submit" class="kmsar-btn kmsar-btn--primary kmsar-btn--lg" style="width: 100%;">
                        Create account
                    </button>
                </div>
            </form>

            <p class="kmsar-login-lead" style="margin-top: 1.25rem; margin-bottom: 0; text-align: center;">
                Already have an account?
                <a href="{{ route('login') }}" style="color: #1E3A8A; font-weight: 600; text-decoration: underline;">Sign in</a>
            </p>

            <p class="kmsar-login-ldap">
                Authentication is checked against the university <strong style="color: var(--color-text-secondary); font-weight: 600;">LDAP</strong> directory.
                Use the same employee number and password as your AUF network account. Contact IT if you cannot sign in.
            </p>
        </div>
    </div>
</div>
@endsection
