<?php

namespace App\Mail;

class ResearchApprovedFacultyMail extends ResearchNotificationMail
{
    protected function subjectLine(): string
    {
        return 'Research Approved! — '.$this->titleSnippet();
    }

    protected function heading(): string
    {
        return 'Research Approved';
    }

    protected function bodyText(): string
    {
        return 'Congratulations! Your research has been approved by OVPRI.';
    }

    protected function actionUrl(): ?string
    {
        return route('research.show', $this->research);
    }

    protected function actionLabel(): string
    {
        return 'View Approved Research';
    }

    protected function emailView(): string
    {
        return 'emails.research-approved-faculty';
    }
}
