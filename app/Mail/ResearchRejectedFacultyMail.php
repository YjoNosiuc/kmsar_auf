<?php

namespace App\Mail;

use App\Models\Research;

class ResearchRejectedFacultyMail extends ResearchNotificationMail
{
    public function __construct(Research $research, string $remarks)
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
        return 'Your research has been rejected. You may revise and resubmit.';
    }

    protected function actionUrl(): ?string
    {
        return route('research.show', $this->research);
    }

    protected function actionLabel(): string
    {
        return 'Revise Research';
    }

    protected function emailView(): string
    {
        return 'emails.research-rejected-faculty';
    }
}
