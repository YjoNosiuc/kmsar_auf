<?php

namespace App\Support;

use App\Jobs\SendKmsarEmail;
use Illuminate\Mail\Mailable;

class SafeMail
{
    /**
     * Queue a KMSAR email. Use $delaySeconds to stagger sends (Mailtrap rate limits).
     */
    public static function send(string $to, Mailable $mailable, int $delaySeconds = 0): void
    {
        if ($to === '') {
            return;
        }

        $job = new SendKmsarEmail($to, $mailable);

        if ($delaySeconds > 0) {
            $job->delay(now()->addSeconds($delaySeconds));
        }

        dispatch($job);
    }
}
