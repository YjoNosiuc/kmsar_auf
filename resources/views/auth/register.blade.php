@extends('layouts.auth')

@section('title', 'Create account — ' . config('app.name', 'KMSAR'))

@push('styles')
<style>
    html:has(.kmsar-login--register),
    html:has(.kmsar-login--register) body.kmsar-auth-shell {
        height: 100%;
        overflow: hidden;
    }
    .kmsar-auth-shell {
        margin: 0;
        height: 100vh;
        overflow: hidden;
        font-family: var(--font-sans, 'Inter', system-ui, sans-serif);
        background: var(--color-surface, #F8FAFC);
    }
    .kmsar-login {
        display: flex;
        height: 100%;
        min-height: 0;
        flex-direction: column;
        overflow: hidden;
    }
    @media (min-width: 768px) {
        .kmsar-login {
            flex-direction: row;
        }
    }
    .kmsar-login-brand {
        flex: 0 0 auto;
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
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        padding: 1.25rem 1.5rem;
        background: var(--color-card, #fff);
    }
    @media (min-width: 768px) {
        .kmsar-login-brand {
            flex: 0 0 40%;
            min-height: 0;
            height: 100%;
        }
        .kmsar-login-panel {
            flex: 1 1 60%;
            height: 100%;
            box-shadow: -12px 0 40px rgba(15, 23, 42, 0.06);
        }
    }
    .kmsar-login-card {
        width: 100%;
        max-width: 36rem;
    }
    .kmsar-login-heading {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-text-primary, #0F172A);
        margin-bottom: 0.15rem;
    }
    .kmsar-login-lead {
        font-size: 0.8125rem;
        color: var(--color-text-secondary, #475569);
        margin-bottom: 0.75rem;
    }
    .kmsar-login--register .kmsar-form-group {
        margin-bottom: 0.55rem;
    }
    .kmsar-login--register .kmsar-form-label {
        margin-bottom: 0.2rem;
        font-size: 0.75rem;
    }
    .kmsar-login--register .kmsar-input,
    .kmsar-login--register .kmsar-select {
        padding: 0.4rem 0.7rem;
    }
    .kmsar-login--register .kmsar-form-row-2,
    .kmsar-login--register .kmsar-form-row-3 {
        gap: 0.65rem;
    }
    .kmsar-login--register .kmsar-form-row-2,
    .kmsar-login--register .kmsar-form-row-3 {
        grid-template-columns: 1fr 1fr;
    }
    .kmsar-login--register .kmsar-form-row-3 {
        grid-template-columns: 1fr 1fr 1fr;
    }
    .kmsar-login--register .kmsar-btn--lg {
        padding: 0.55rem 1.25rem;
        font-size: 0.875rem;
    }
    .kmsar-login--register .kmsar-register-submit {
        margin-top: 0.5rem;
        margin-bottom: 0;
    }
    .kmsar-login--register .kmsar-register-signin {
        margin-top: 0.75rem;
        margin-bottom: 0;
        text-align: center;
    }
    .kmsar-login--register .kmsar-register-submit-btn {
        width: 100%;
    }
    .kmsar-login--register .kmsar-register-signin-link {
        color: #1E3A8A;
        font-weight: 600;
        text-decoration: underline;
    }
    [x-cloak] { display: none !important; }
    @media (max-width: 767px) {
        .kmsar-login-brand {
            padding: 0.75rem 1rem;
        }
        .kmsar-login-brand-title {
            font-size: 1.05rem;
            margin-bottom: 0;
        }
        .kmsar-login-brand-sub {
            display: none;
        }
        .kmsar-login-brand-inst {
            margin-bottom: 0.2rem;
        }
        .kmsar-login-panel {
            padding: 0.85rem 0.85rem 1rem;
        }
        .kmsar-login-card {
            width: 100%;
            max-width: 100%;
        }
    }
</style>
@endpush

@section('content')
<div class="kmsar-login kmsar-login--register">
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

            @if (request()->boolean('expired'))
                <x-alert type="warning" class="kmsar-form-group" :message="__('Your session expired. Please submit the form again.')" />
            @endif

            @if ($errors->any())
                <x-alert type="danger" class="kmsar-form-group">
                    <ul style="margin: 0; padding-left: 1.125rem; font-size: var(--text-sm); line-height: 1.5;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            <form
                method="POST"
                action="{{ route('register.store') }}"
                x-data="{
                    selectedCollegeId: @js(old('college_id') ? (string) old('college_id') : ''),
                    selectedProgramId: @js(old('program_id') ? (string) old('program_id') : ''),
                    userType: @js(old('user_type', '')),
                    programs: [],
                    programsUrl: @js(route('api.programs')),
                    get idLabel() {
                        if (this.userType === 'student') return 'Student Number';
                        if (this.userType === 'external_affiliate') return 'ID Number (optional)';
                        return 'ID Number';
                    },
                    get showInstitution() {
                        return this.userType === 'external_affiliate';
                    },
                    get idRequired() {
                        return this.userType === 'faculty' || this.userType === 'staff' || this.userType === 'student';
                    },
                    init() {
                        if (this.selectedCollegeId) {
                            this.loadPrograms(this.selectedCollegeId);
                        }
                    },
                    loadPrograms(collegeId) {
                        this.programs = [];
                        if (!collegeId) return;
                        fetch(`${this.programsUrl}?college_id=${encodeURIComponent(collegeId)}`, {
                            headers: { Accept: 'application/json' },
                        })
                            .then((r) => r.json())
                            .then((data) => { this.programs = Array.isArray(data) ? data : []; });
                    }
                }"
            >
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
                        <label class="kmsar-form-label" for="user_type">
                            User Type <span class="kmsar-form-required">*</span>
                        </label>
                        <select id="user_type" name="user_type" class="kmsar-select" required x-model="userType">
                            <option value="">— Select user type —</option>
                            <option value="faculty">Faculty</option>
                            <option value="staff">Staff</option>
                            <option value="student">Student</option>
                            <option value="external_affiliate">External Affiliate</option>
                        </select>
                        @error('user_type')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="kmsar-form-row-2">
                    <div class="kmsar-form-group" x-show="userType !== 'external_affiliate'" x-cloak>
                        <label class="kmsar-form-label" for="employee_number">
                            <span x-text="idLabel"></span>
                            <span class="kmsar-form-required" x-show="idRequired">*</span>
                        </label>
                        <input
                            id="employee_number"
                            type="text"
                            name="employee_number"
                            value="{{ old('employee_number') }}"
                            class="kmsar-input"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="10"
                            placeholder="Up to 10 digits"
                            x-bind:required="idRequired"
                            autocomplete="off"
                            x-on:input="$event.target.value = $event.target.value.replace(/[^0-9]/g, '').slice(0, 10)"
                        >
                        @error('employee_number')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="kmsar-form-group" x-show="showInstitution" x-cloak>
                        <label class="kmsar-form-label" for="institution">Institution</label>
                        <input
                            id="institution"
                            type="text"
                            name="institution"
                            value="{{ old('institution') }}"
                            class="kmsar-input"
                            placeholder="e.g. De La Salle University"
                            autocomplete="organization"
                        >
                        @error('institution')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
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
                </div>

                <div class="kmsar-form-row-2">
                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label" for="college_id">College/Office <span class="kmsar-form-required">*</span></label>
                        <select
                            id="college_id"
                            name="college_id"
                            class="kmsar-select"
                            required
                            x-model="selectedCollegeId"
                            x-on:change="selectedProgramId = ''; loadPrograms($event.target.value)"
                        >
                            <option value="">{{ __('— Select College/Office —') }}</option>
                            @foreach ($colleges as $college)
                                <option value="{{ $college->id }}">
                                    {{ $college->code }} — {{ $college->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label" for="program_id">Program/Dept</label>
                        <input type="hidden" x-bind:name="programs.length === 0 ? 'program_id' : null" x-bind:value="selectedProgramId">
                        <select
                            id="program_id"
                            name="program_id"
                            class="kmsar-select"
                            x-model="selectedProgramId"
                            x-bind:disabled="programs.length === 0"
                        >
                            <option value="">{{ __('— Select Program/Dept (optional) —') }}</option>
                            <template x-for="program in programs" :key="program.id">
                                <option :value="String(program.id)" x-text="program.code + ' — ' + program.name"></option>
                            </template>
                        </select>
                    </div>
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

                <div class="kmsar-form-group kmsar-register-submit">
                    <button type="submit" class="kmsar-btn kmsar-btn--primary kmsar-btn--lg kmsar-register-submit-btn">
                        Create account
                    </button>
                </div>
            </form>

            <p class="kmsar-login-lead kmsar-register-signin">
                Already have an account?
                <a href="{{ route('login') }}" class="kmsar-register-signin-link">Sign in</a>
            </p>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const form = document.querySelector('form[action*="register"]');
        const meta = document.querySelector('meta[name="csrf-token"]');

        function syncCsrf(token) {
            if (! token) {
                return;
            }
            if (meta) {
                meta.setAttribute('content', token);
            }
            const input = form?.querySelector('input[name="_token"]');
            if (input) {
                input.value = token;
            }
        }

        form?.addEventListener('submit', function () {
            syncCsrf(meta?.getAttribute('content'));
        });

        try {
            localStorage.removeItem('kmsar-idle-deadline');
            localStorage.removeItem('kmsar-last-activity');
            localStorage.removeItem('kmsar-auth-logout');
        } catch (e) {
            /* private mode */
        }

        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    })();
</script>
@endpush
@endsection
