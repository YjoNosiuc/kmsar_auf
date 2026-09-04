<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSmtpSettingsRequest;
use App\Services\SmtpSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class SmtpSettingsController extends Controller
{
    public function __construct(
        private SmtpSettingsService $smtpSettingsService,
    ) {}

    public function edit(): View
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $settings = $this->smtpSettingsService->ensureDefaults();

        return view('admin.smtp-settings.edit', [
            'settings' => $settings,
            'presets' => config('kmsar.smtp_presets', []),
        ]);
    }

    public function update(UpdateSmtpSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_enabled'] = $request->boolean('is_enabled');

        if (($validated['mail_encryption'] ?? '') === '') {
            $validated['mail_encryption'] = null;
        }

        $this->smtpSettingsService->updateFromRequest($validated, $request->user());
        $this->smtpSettingsService->applyToConfig();

        return redirect()
            ->route('admin.smtp-settings.edit')
            ->with('success', __('SMTP settings saved successfully.'));
    }

    public function test(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $validated = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        try {
            $this->smtpSettingsService->sendTestEmail($validated['test_email']);

            return redirect()
                ->route('admin.smtp-settings.edit')
                ->with('success', __('Test email sent to :email.', ['email' => $validated['test_email']]));
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.smtp-settings.edit')
                ->with('error', __('Test email failed: :message', ['message' => $e->getMessage()]));
        }
    }
}
