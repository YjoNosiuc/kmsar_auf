@extends('layouts.app')
@section('title', 'My Profile')
@section('navbar-context', 'My Profile')

@section('content')

@if(session('success'))
    <x-alert type="success"
             class="mb-4">
        {{ session('success') }}
    </x-alert>
@endif

{{-- Page header --}}
<div class="kmsar-page-header"
     style="margin-bottom:var(--space-5);">
    <div>
        <h1 class="kmsar-h2">My Profile</h1>
        <p class="kmsar-body">
            Manage your personal information and password
        </p>
    </div>
</div>

<div class="kmsar-two-col"
     style="align-items:flex-start;">

    {{-- LEFT — Personal info --}}
    <x-card title="Personal information">
        <form method="POST"
              action="{{ route('profile.update') }}">
            @csrf
            @method('PATCH')

            @if($errors->profile->any())
                <x-alert type="danger" class="mb-4">
                    <ul style="margin:0;
                               padding-left:1.125rem;
                               font-size:var(--text-sm);">
                        @foreach($errors->profile->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            <div class="kmsar-form-row-3">
                <div class="kmsar-form-group">
                    <label class="kmsar-form-label" for="profile_first_name">
                        First Name
                        <span class="kmsar-form-required" aria-hidden="true">*</span>
                    </label>
                    <input type="text"
                           name="first_name"
                           id="profile_first_name"
                           class="kmsar-input {{ $errors->profile->has('first_name') ? 'kmsar-input--error' : '' }}"
                           value="{{ old('first_name', $user->first_name) }}"
                           style="text-transform: uppercase;"
                           required>
                    @error('first_name', 'profile')
                        <p class="kmsar-form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="kmsar-form-group">
                    <label class="kmsar-form-label" for="profile_last_name">
                        Last Name
                        <span class="kmsar-form-required" aria-hidden="true">*</span>
                    </label>
                    <input type="text"
                           name="last_name"
                           id="profile_last_name"
                           class="kmsar-input {{ $errors->profile->has('last_name') ? 'kmsar-input--error' : '' }}"
                           value="{{ old('last_name', $user->last_name) }}"
                           style="text-transform: uppercase;"
                           required>
                    @error('last_name', 'profile')
                        <p class="kmsar-form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="kmsar-form-group">
                    <label class="kmsar-form-label" for="profile_middle_name">
                        Middle Name
                    </label>
                    <input type="text"
                           name="middle_name"
                           id="profile_middle_name"
                           class="kmsar-input {{ $errors->profile->has('middle_name') ? 'kmsar-input--error' : '' }}"
                           value="{{ old('middle_name', $user->middle_name) }}"
                           style="text-transform: uppercase;">
                    @error('middle_name', 'profile')
                        <p class="kmsar-form-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="kmsar-form-group">
                <label class="kmsar-form-label" for="profile_suffix">
                    Suffix
                </label>
                <input type="text"
                       name="suffix"
                       id="profile_suffix"
                       class="kmsar-input {{ $errors->profile->has('suffix') ? 'kmsar-input--error' : '' }}"
                       value="{{ old('suffix', $user->suffix) }}"
                       placeholder="Jr., Sr., III, etc."
                       style="text-transform: uppercase;">
                @error('suffix', 'profile')
                    <p class="kmsar-form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="kmsar-form-group">
                <label class="kmsar-form-label" for="profile_email">
                    Email Address
                    <span class="kmsar-form-required" aria-hidden="true">*</span>
                </label>
                <input type="email"
                       name="email"
                       id="profile_email"
                       class="kmsar-input {{ $errors->profile->has('email') ? 'kmsar-input--error' : '' }}"
                       value="{{ old('email', $user->email) }}"
                       style="text-transform: none;"
                       required>
                @error('email', 'profile')
                    <p class="kmsar-form-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Read-only fields --}}
            <div class="kmsar-form-group">
                <label class="kmsar-form-label">
                    Employee Number
                </label>
                <input type="text"
                       class="kmsar-input"
                       value="{{ $user->employee_number }}"
                       disabled
                       style="background:var(--color-surface);
                              color:var(--color-text-muted);
                              cursor:not-allowed;">
                <p class="kmsar-form-hint">
                    Employee number cannot be changed.
                </p>
            </div>

            <div class="kmsar-form-group">
                <label class="kmsar-form-label">College</label>
                <input type="text"
                       class="kmsar-input"
                       value="{{ $user->college?->name ?? '—' }}"
                       disabled
                       style="background:var(--color-surface);
                              color:var(--color-text-muted);
                              cursor:not-allowed;">
                <p class="kmsar-form-hint">
                    Contact admin to change college.
                </p>
            </div>

            <div class="kmsar-form-group">
                <label class="kmsar-form-label">Role</label>
                <input type="text"
                       class="kmsar-input"
                       value="{{ ucwords(str_replace('_', ' ', $user->roles->first()?->name ?? '—')) }}"
                       disabled
                       style="background:var(--color-surface);
                              color:var(--color-text-muted);
                              cursor:not-allowed;">
            </div>

            <div style="display:flex;
                        justify-content:flex-end;
                        margin-top:1.5rem;
                        padding-top:1rem;
                        border-top:1px solid var(--color-border);">
                <button type="submit"
                        class="kmsar-btn kmsar-btn--primary">
                    Save changes
                </button>
            </div>
        </form>
    </x-card>

    {{-- RIGHT — Change password --}}
    <x-card title="Change password">
        <form method="POST"
              action="{{ route('profile.password') }}">
            @csrf
            @method('PATCH')

            @if($errors->password->any())
                <x-alert type="danger" class="mb-4">
                    <ul style="margin:0;
                               padding-left:1.125rem;
                               font-size:var(--text-sm);">
                        @foreach($errors->password->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            <div class="kmsar-form-group">
                <label class="kmsar-form-label" for="profile_current_password">
                    Current Password
                    <span class="kmsar-form-required" aria-hidden="true">*</span>
                </label>
                <input type="password"
                       name="current_password"
                       id="profile_current_password"
                       class="kmsar-input {{ $errors->password->has('current_password') ? 'kmsar-input--error' : '' }}"
                       style="text-transform: none;"
                       required
                       autocomplete="current-password">
                @error('current_password', 'password')
                    <p class="kmsar-form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="kmsar-form-group">
                <label class="kmsar-form-label" for="profile_password">
                    New Password
                    <span class="kmsar-form-required" aria-hidden="true">*</span>
                </label>
                <input type="password"
                       name="password"
                       id="profile_password"
                       class="kmsar-input {{ $errors->password->has('password') ? 'kmsar-input--error' : '' }}"
                       placeholder="Min. 8 characters"
                       style="text-transform: none;"
                       required
                       autocomplete="new-password">
                @error('password', 'password')
                    <p class="kmsar-form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="kmsar-form-group">
                <label class="kmsar-form-label" for="profile_password_confirmation">
                    Confirm New Password
                    <span class="kmsar-form-required" aria-hidden="true">*</span>
                </label>
                <input type="password"
                       name="password_confirmation"
                       id="profile_password_confirmation"
                       class="kmsar-input {{ $errors->password->has('password_confirmation') ? 'kmsar-input--error' : '' }}"
                       placeholder="Repeat new password"
                       style="text-transform: none;"
                       required
                       autocomplete="new-password">
                @error('password_confirmation', 'password')
                    <p class="kmsar-form-error">{{ $message }}</p>
                @enderror
            </div>

            <div style="display:flex;
                        justify-content:flex-end;
                        margin-top:1.5rem;
                        padding-top:1rem;
                        border-top:1px solid var(--color-border);">
                <button type="submit"
                        class="kmsar-btn kmsar-btn--primary">
                    Change password
                </button>
            </div>
        </form>
    </x-card>

</div>
@endsection
