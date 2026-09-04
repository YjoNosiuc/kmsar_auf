<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends notification email but logs and swallows transport errors so
 * in-app notifications still succeed when SMTP is misconfigured.
 */
class ResilientMailChannel
{
    public function __construct(
        protected MailChannel $mailChannel,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        try {
            $this->mailChannel->send($notifiable, $notification);
        } catch (Throwable $e) {
            Log::warning('Notification email failed: '.$e->getMessage(), [
                'notifiable_id' => $notifiable->id ?? null,
                'notification' => $notification::class,
            ]);
        }
    }
}
