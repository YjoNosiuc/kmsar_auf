<?php

namespace App\Mail;

use App\Models\Research;
use App\Models\User;

class ResearchApprovedDeanMail extends ResearchNotificationMail
{
    public function __construct(Research $research, public User $dean)
    {
        parent::__construct($research);
    }

    protected function subjectLine(): string
    {
        return 'Research Approved — '.$this->titleSnippet();
    }

    protected function heading(): string
    {
        return 'Research Approved';
    }

    protected function bodyText(): string
    {
        return 'A research from your college has been approved by OVPRI. Faculty: '.$this->authorName().'.';
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
        return 'emails.research-approved-dean';
    }
}
