<?php

namespace App\Mail;

class ResearchEndorsedFacultyMail extends ResearchNotificationMail
{
    protected function subjectLine(): string
    {
        return 'Research Endorsed by Dean — '.$this->titleSnippet();
    }

    protected function heading(): string
    {
        return 'Research Endorsed by Dean';
    }

    protected function bodyText(): string
    {
        return 'Your research has been endorsed by the Dean and forwarded to OVPRI for review.';
    }

    protected function actionUrl(): ?string
    {
        return route('research.show', $this->research);
    }

    protected function actionLabel(): string
    {
        return 'View Research';
    }

    protected function emailView(): string
    {
        return 'emails.research-endorsed-faculty';
    }
}
