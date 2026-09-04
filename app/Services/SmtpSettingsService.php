<?php

namespace App\Services;

use App\Models\SmtpSetting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SmtpSettingsService
{
    public function current(): ?SmtpSetting
    {
        try {
            return SmtpSetting::query()->first();
        } catch (Throwable) {
            return null;
        }
    }

    public function ensureDefaults(): SmtpSetting
    {
        $existing = $this->current();

        if ($existing !== null) {
            return $existing;
        }

        $defaults = config('kmsar.smtp_defaults', []);
        $password = filled(env('MAIL_PASSWORD')) ? (string) env('MAIL_PASSWORD') : null;
        $username = filled($defaults['mail_username'] ?? null)
            ? (string) $defaults['mail_username']
            : (filled(env('MAIL_USERNAME')) ? (string) env('MAIL_USERNAME') : null);

        return SmtpSetting::query()->create([
            'is_enabled' => true,
            'preset' => $defaults['preset'] ?? 'mailtrap_sandbox',
            'mail_mailer' => $defaults['mail_mailer'] ?? 'smtp',
            'mail_host' => $defaults['mail_host'] ?? 'sandbox.smtp.mailtrap.io',
            'mail_port' => (int) ($defaults['mail_port'] ?? 2525),
            'mail_username' => $username,
            'mail_password' => $password,
            'mail_encryption' => $defaults['mail_encryption'] ?? 'tls',
            'mail_from_address' => $defaults['mail_from_address'] ?? 'noreply@kmsar.auf.edu.ph',
            'mail_from_name' => $defaults['mail_from_name'] ?? 'KMSAR',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function presetFields(string $preset): array
    {
        $presets = config('kmsar.smtp_presets', []);

        return $presets[$preset] ?? $presets['custom'] ?? [];
    }

    public function applyToConfig(): void
    {
        $settings = $this->current();

        if ($settings === null || ! $settings->is_enabled) {
            return;
        }

        config([
            'mail.default' => $settings->mail_mailer,
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $settings->mail_host,
            'mail.mailers.smtp.port' => $settings->mail_port,
            'mail.mailers.smtp.username' => $settings->mail_username,
            'mail.mailers.smtp.encryption' => $settings->mail_encryption ?: null,
            'mail.from.address' => $settings->mail_from_address,
            'mail.from.name' => $settings->mail_from_name,
        ]);

        if ($settings->hasPassword()) {
            config(['mail.mailers.smtp.password' => $settings->mail_password]);
        }
    }

    public function updateFromRequest(array $validated, User $actor): SmtpSetting
    {
        $settings = $this->ensureDefaults();

        $payload = [
            'is_enabled' => (bool) ($validated['is_enabled'] ?? true),
            'preset' => $validated['preset'],
            'mail_mailer' => $validated['mail_mailer'],
            'mail_host' => $validated['mail_host'],
            'mail_port' => (int) $validated['mail_port'],
            'mail_username' => $validated['mail_username'] ?? null,
            'mail_encryption' => $validated['mail_encryption'] ?? null,
            'mail_from_address' => $validated['mail_from_address'],
            'mail_from_name' => $validated['mail_from_name'],
            'updated_by' => $actor->id,
        ];

        if (filled($validated['mail_password'] ?? null)) {
            $payload['mail_password'] = $validated['mail_password'];
        }

        $settings->update($payload);

        return $settings->fresh();
    }

    public function sendTestEmail(string $to): void
    {
        $this->applyToConfig();

        Mail::raw(
            'KMSAR SMTP test — if you receive this message, the configured mail settings are working.',
            function ($message) use ($to) {
                $message->to($to)->subject('KMSAR SMTP test');
            }
        );
    }
}
