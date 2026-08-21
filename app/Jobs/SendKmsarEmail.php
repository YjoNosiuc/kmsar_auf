<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendKmsarEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public readonly string $to,
        public readonly Mailable $mailable,
    ) {}

    public function handle(): void
    {
        try {
            Mail::to($this->to)->send($this->mailable);
        } catch (Throwable $e) {
            Log::warning('Email failed: '.$e->getMessage(), [
                'to' => $this->to,
                'mailable' => $this->mailable::class,
            ]);
        }
    }
}
