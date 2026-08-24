<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EmailVerificationMail extends Mailable
{

    public function __construct(
        public string $otp,
        public string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your KMSAR Email Address',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-verification',
        );
    }
}
