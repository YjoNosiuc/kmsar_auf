<?php

namespace App\Console\Commands;

use App\Services\SmtpSettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestMailCommand extends Command
{
    protected $signature = 'kmsar:test-mail {email : Recipient address}';

    protected $description = 'Send a test email using current MAIL_* settings (diagnose SMTP)';

    public function handle(): int
    {
        app(SmtpSettingsService::class)->applyToConfig();

        $to = (string) $this->argument('email');

        $this->line('MAIL_MAILER: '.config('mail.default'));
        $this->line('MAIL_HOST: '.config('mail.mailers.smtp.host'));
        $this->line('MAIL_USERNAME: '.config('mail.mailers.smtp.username'));
        $this->line('MAIL_FROM: '.config('mail.from.address'));
        $this->line('SMTP source: '.(app(SmtpSettingsService::class)->current()?->is_enabled ? 'database (admin SMTP settings)' : '.env only'));
        $this->line('QUEUE (research notifications are sync — worker not required): '.config('queue.default'));
        $this->newLine();
        $this->info("Sending test email to {$to}...");

        try {
            Mail::raw('KMSAR SMTP test — if you receive this, mail is configured correctly.', function ($message) use ($to) {
                $message->to($to)->subject('KMSAR mail test');
            });

            $this->info('SUCCESS: Email accepted by the mail transport.');
            $this->comment('Check the inbox (and spam). For YOPmail use https://yopmail.com with the inbox name before @.');
            $this->comment('Mailtrap sandbox captures mail in your Mailtrap inbox, not the recipient\'s real inbox.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('FAILED: '.$e->getMessage());
            $this->newLine();
            $this->warn('Common fixes:');
            $this->line('  1. Use a Gmail App Password (16 chars), not your normal Gmail password.');
            $this->line('  2. Enable 2-Step Verification on the Google account first.');
            $this->line('  3. After editing .env run: php artisan config:clear');

            return self::FAILURE;
        }
    }
}
