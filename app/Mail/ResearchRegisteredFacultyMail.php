<?php

namespace App\Mail;

class ResearchRegisteredFacultyMail extends ResearchNotificationMail
{
    protected function subjectLine(): string
    {
        return 'Research Registered — '.$this->titleSnippet();
    }

    protected function heading(): string
    {
        return 'Research Registered';
    }

    protected function bodyText(): string
    {
        return 'Your existing research has been registered in KMSAR and is now available for completion updates when you are ready.';
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
        return 'emails.research-submitted-faculty';
    }
}
