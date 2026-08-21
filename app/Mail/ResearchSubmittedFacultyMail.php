<?php

namespace App\Mail;

class ResearchSubmittedFacultyMail extends ResearchNotificationMail
{
    protected function subjectLine(): string
    {
        return 'Research Successfully Submitted — '.$this->titleSnippet();
    }

    protected function heading(): string
    {
        return 'Research Successfully Submitted';
    }

    protected function bodyText(): string
    {
        return 'Your research has been submitted for Dean review. The Dean of '.$this->collegeName().' will review your submission.';
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
