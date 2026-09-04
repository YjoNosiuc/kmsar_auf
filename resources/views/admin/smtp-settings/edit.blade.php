@extends('layouts.app')

@section('title', __('SMTP Settings — ') . config('app.name', 'KMSAR'))

@push('styles')
<style>
    .kmsar-switch-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-4);
        padding: 0.625rem 0.875rem;
        border: 1.5px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface);
    }
    .kmsar-switch {
        position: relative;
        width: 2.75rem;
        height: 1.5rem;
        flex-shrink: 0;
    }
    .kmsar-switch input.sr-only-check {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }
    .kmsar-switch-track {
        position: absolute;
        inset: 0;
        border-radius: var(--radius-full);
        background: var(--color-border-strong);
        transition: background var(--transition);
        cursor: pointer;
    }
    .kmsar-switch-thumb {
        position: absolute;
        top: 0.1875rem;
        left: 0.1875rem;
        width: 1.125rem;
        height: 1.125rem;
        border-radius: 50%;
        background: var(--color-card);
        box-shadow: var(--shadow-sm);
        transition: transform var(--transition);
    }
    .kmsar-switch input:checked + .kmsar-switch-track {
        background: var(--color-primary);
    }
    .kmsar-switch input:checked + .kmsar-switch-track .kmsar-switch-thumb {
        transform: translateX(1.25rem);
    }
    .kmsar-switch input:focus-visible + .kmsar-switch-track {
        outline: 2px solid var(--color-primary);
        outline-offset: 2px;
    }
    .kmsar-info-banner {
        padding: 16px 20px;
        background: #F0F9FF;
        border: 1px solid #BAE6FD;
        border-radius: var(--radius-md);
        margin-bottom: var(--space-4);
    }
    .kmsar-info-banner p {
        margin: 0;
        font-size: var(--text-sm);
        color: #0C4A6E;
    }
</style>
@endpush

@section('navbar-context')
    {{ __('Administration') }}
@endsection

@section('content')
    <x-page-header
        :title="__('SMTP Settings')"
        :subtitle="__('Configure outbound email for KMSAR notifications. Defaults to Mailtrap sandbox for development.')"
    />

    @if (session('success'))
        <x-alert variant="success" class="mb-4">{{ session('success') }}</x-alert>
    @endif
    @if (session('error'))
        <x-alert variant="danger" class="mb-4">{{ session('error') }}</x-alert>
    @endif

    <div class="kmsar-info-banner">
        <p class="kmsar-body">
            {{ __('When enabled, these settings override .env mail configuration for the whole application. Priority notification emails (dean endorse → OVPRI, OVPRI approve → faculty) use this SMTP connection.') }}
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('admin.smtp-settings.update') }}"
        class="kmsar-card p-6"
        x-data="smtpSettingsForm(@js([
            'preset' => old('preset', $settings->preset),
            'mail_mailer' => old('mail_mailer', $settings->mail_mailer),
            'mail_host' => old('mail_host', $settings->mail_host),
            'mail_port' => old('mail_port', $settings->mail_port),
            'mail_username' => old('mail_username', $settings->mail_username),
            'mail_encryption' => old('mail_encryption', $settings->mail_encryption ?? 'tls'),
            'mail_from_address' => old('mail_from_address', $settings->mail_from_address),
            'mail_from_name' => old('mail_from_name', $settings->mail_from_name),
            'is_enabled' => old('is_enabled', $settings->is_enabled) ? true : false,
        ]), @js($presets))"
        @submit="is_enabled = document.getElementById('is_enabled').checked"
    >
        @csrf
        @method('PUT')

        <div class="kmsar-switch-row mb-6">
            <div>
                <div class="kmsar-card-title">{{ __('Use database SMTP settings') }}</div>
                <p class="kmsar-body mt-1" style="font-size:var(--text-sm);color:var(--color-text-muted);">
                    {{ __('When off, Laravel falls back to .env mail settings only.') }}
                </p>
            </div>
            <label class="kmsar-switch">
                <input type="checkbox" id="is_enabled" name="is_enabled" value="1" class="sr-only-check"
                       x-model="is_enabled">
                <span class="kmsar-switch-track"><span class="kmsar-switch-thumb"></span></span>
            </label>
        </div>

        <div class="mb-6">
            <label for="preset" class="block kmsar-body font-semibold mb-2">{{ __('Configuration preset') }}</label>
            <select id="preset" name="preset" class="kmsar-select w-full max-w-xl" x-model="preset" @change="applyPreset()">
                @foreach ($presets as $key => $preset)
                    <option value="{{ $key }}">{{ $preset['label'] ?? $key }}</option>
                @endforeach
            </select>
            <p class="kmsar-body mt-2" style="font-size:var(--text-sm);color:var(--color-text-muted);" x-text="presetDescription()"></p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 mb-6">
            <div>
                <label for="mail_mailer" class="block kmsar-body font-semibold mb-2">{{ __('Mail driver') }}</label>
                <select id="mail_mailer" name="mail_mailer" class="kmsar-select w-full" x-model="mail_mailer">
                    <option value="smtp">SMTP</option>
                    <option value="log">{{ __('Log only (no send)') }}</option>
                </select>
            </div>
            <div>
                <label for="mail_encryption" class="block kmsar-body font-semibold mb-2">{{ __('Encryption') }}</label>
                <select id="mail_encryption" name="mail_encryption" class="kmsar-select w-full" x-model="mail_encryption">
                    <option value="tls">TLS</option>
                    <option value="ssl">SSL</option>
                    <option value="">{{ __('None') }}</option>
                </select>
            </div>
            <div>
                <label for="mail_host" class="block kmsar-body font-semibold mb-2">{{ __('SMTP host') }}</label>
                <input type="text" id="mail_host" name="mail_host" class="kmsar-input w-full" x-model="mail_host" required>
                @error('mail_host')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="mail_port" class="block kmsar-body font-semibold mb-2">{{ __('SMTP port') }}</label>
                <input type="number" id="mail_port" name="mail_port" class="kmsar-input w-full" x-model="mail_port" min="1" max="65535" required>
                @error('mail_port')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="mail_username" class="block kmsar-body font-semibold mb-2">{{ __('SMTP username') }}</label>
                <input type="text" id="mail_username" name="mail_username" class="kmsar-input w-full" x-model="mail_username" autocomplete="off">
                @error('mail_username')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="mail_password" class="block kmsar-body font-semibold mb-2">{{ __('SMTP password') }}</label>
                <input type="password" id="mail_password" name="mail_password" class="kmsar-input w-full" autocomplete="new-password"
                       placeholder="{{ $settings->hasPassword() ? __('Leave blank to keep current password') : __('Required for sending') }}">
                @error('mail_password')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="mail_from_address" class="block kmsar-body font-semibold mb-2">{{ __('From email address') }}</label>
                <input type="email" id="mail_from_address" name="mail_from_address" class="kmsar-input w-full" x-model="mail_from_address" required>
                @error('mail_from_address')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="mail_from_name" class="block kmsar-body font-semibold mb-2">{{ __('From name') }}</label>
                <input type="text" id="mail_from_name" name="mail_from_name" class="kmsar-input w-full" x-model="mail_from_name" required>
                @error('mail_from_name')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        @if ($settings->updated_at)
            <p class="kmsar-body mb-6" style="font-size:var(--text-sm);color:var(--color-text-muted);">
                {{ __('Last updated') }}: {{ $settings->updated_at->format('M d, Y h:i A') }}
                @if ($settings->updatedByUser)
                    · {{ $settings->updatedByUser->name }}
                @endif
            </p>
        @endif

        <div class="flex flex-wrap gap-3">
            <x-button type="submit" variant="primary">{{ __('Save SMTP settings') }}</x-button>
        </div>
    </form>

    <x-card title="{{ __('Send test email') }}" class="mt-6">
        <form method="POST" action="{{ route('admin.smtp-settings.test') }}" class="flex flex-wrap gap-3 items-end">
            @csrf
            <div class="flex-1 min-w-[240px]">
                <label for="test_email" class="block kmsar-body font-semibold mb-2">{{ __('Recipient email') }}</label>
                <input type="email" id="test_email" name="test_email" class="kmsar-input w-full"
                       value="{{ old('test_email', auth()->user()->email) }}" required>
            </div>
            <x-button type="submit" variant="outline">{{ __('Send test') }}</x-button>
        </form>
        <p class="kmsar-body mt-3" style="font-size:var(--text-sm);color:var(--color-text-muted);">
            {{ __('Mailtrap sandbox captures test mail in your Mailtrap inbox, not the recipient\'s real inbox.') }}
        </p>
    </x-card>
@endsection

@push('scripts')
<script>
function smtpSettingsForm(initial, presets) {
    return {
        ...initial,
        presets,
        applyPreset() {
            const preset = this.presets[this.preset];
            if (!preset) {
                return;
            }
            if (preset.mail_mailer) this.mail_mailer = preset.mail_mailer;
            if (preset.mail_host !== undefined) this.mail_host = preset.mail_host;
            if (preset.mail_port) this.mail_port = preset.mail_port;
            if (preset.mail_encryption !== undefined) this.mail_encryption = preset.mail_encryption;
            if (preset.mail_username !== undefined && this.preset !== 'custom') {
                this.mail_username = preset.mail_username;
            }
        },
        presetDescription() {
            return this.presets[this.preset]?.description ?? '';
        },
    };
}
</script>
@endpush
