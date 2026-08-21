<?php

namespace App\Mail;

use App\Models\Research;
use App\Models\User;

class ResearchRejectedDeanMail extends ResearchNotificationMail
{
    public function __construct(Research $research, public User $dean, string $remarks)
    {
        parent::__construct($research, $remarks);
    }

    protected function subjectLine(): string
    {
        return 'Research Rejected — '.$this->titleSnippet();
    }

    protected function heading(): string
    {
        return 'Research Rejected';
    }

    protected function bodyText(): string
    {
        return 'A research from your college has been rejected. Faculty: '.$this->authorName().'.';
    }

    protected function actionUrl(): ?string
    {
        return route('approval.queue');
    }

    protected function actionLabel(): string
    {
        return 'View Research';
    }

    protected function recipientName(): string
    {
        return $this->dean->name ?? 'Dean';
    }

    protected function emailView(): string
    {
        return 'emails.research-rejected-dean';
    }
}
