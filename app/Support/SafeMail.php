<?php

namespace App\Support;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SafeMail
{
    /**
     * Send synchronously. Use $delaySeconds to pause before send (Mailtrap free tier rate limit).
     */
    public static function send(string $to, Mailable $mailable, int $delaySeconds = 0): void
    {
        if ($to === '') {
            return;
        }

        if ($delaySeconds > 0) {
            sleep($delaySeconds);
        }

        try {
            Mail::to($to)->send($mailable);
        } catch (Throwable $e) {
            Log::warning('Email failed: '.$e->getMessage(), [
                'to' => $to,
                'mailable' => $mailable::class,
            ]);
        }
    }
}
