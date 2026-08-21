<?php

namespace App\Mail;

use App\Models\Research;

class ResearchReturnedFacultyMail extends ResearchNotificationMail
{
    public function __construct(Research $research, string $remarks)
    {
        parent::__construct($research, $remarks);
    }

    protected function subjectLine(): string
    {
        return 'Research Returned for Revision — '.$this->titleSnippet();
    }

    protected function heading(): string
    {
        return 'Research Returned for Revision';
    }

    protected function bodyText(): string
    {
        return 'Your research has been returned by the Dean for revision.';
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
        return 'emails.research-returned-faculty';
    }
}
