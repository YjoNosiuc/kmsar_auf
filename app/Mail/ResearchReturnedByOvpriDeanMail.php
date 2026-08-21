<?php

namespace App\Mail;

use App\Models\Research;
use App\Models\User;

class ResearchReturnedByOvpriDeanMail extends ResearchNotificationMail
{
    public function __construct(Research $research, public User $dean, string $remarks)
    {
        parent::__construct($research, $remarks);
    }

    protected function subjectLine(): string
    {
        return 'Research Returned by OVPRI — '.$this->titleSnippet();
    }

    protected function heading(): string
    {
        return 'Research Returned by OVPRI';
    }

    protected function bodyText(): string
    {
        return 'OVPRI has returned a research from your college for revision. Faculty: '.$this->authorName().'.';
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
        return 'emails.research-returned-ovpri-dean';
    }
}
