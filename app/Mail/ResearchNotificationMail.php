<?php

namespace App\Mail;

use App\Models\Research;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

abstract class ResearchNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Research $research,
        public ?string $remarks = null,
    ) {
        $this->research->loadMissing(['motherCollege', 'primaryAuthor']);
    }

    abstract protected function subjectLine(): string;

    abstract protected function heading(): string;

    abstract protected function bodyText(): string;

    abstract protected function actionUrl(): ?string;

    abstract protected function actionLabel(): string;

    abstract protected function emailView(): string;

    protected function recipientName(): string
    {
        return $this->authorName();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(
            view: $this->emailView(),
            with: [
                'subjectTitle' => $this->subjectLine(),
                'heading' => $this->heading(),
                'body' => $this->bodyText(),
                'recipientName' => $this->recipientName(),
                'research' => $this->research,
                'remarks' => $this->remarks,
                'actionUrl' => $this->actionUrl(),
                'actionLabel' => $this->actionLabel(),
            ],
        );
    }

    protected function collegeName(): string
    {
        return $this->research->motherCollege?->name ?? 'your college';
    }

    protected function authorName(): string
    {
        return $this->research->primaryAuthor?->name ?? 'Faculty';
    }

    protected function titleSnippet(): string
    {
        return \Illuminate\Support\Str::limit($this->research->title, 80);
    }
}
