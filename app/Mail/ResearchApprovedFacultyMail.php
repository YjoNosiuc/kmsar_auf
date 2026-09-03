<?php

namespace App\Mail;

use App\Support\ResearchStatus;

class ResearchApprovedFacultyMail extends ResearchNotificationMail
{
    protected function subjectLine(): string
    {
        return match ($this->research->status) {
            ResearchStatus::RESEARCH_REGISTERED => 'Research Registered — '.$this->titleSnippet(),
            ResearchStatus::RESEARCH_ACCEPTED => 'Research Accepted — '.$this->titleSnippet(),
            default => 'Research Approved — '.$this->titleSnippet(),
        };
    }

    protected function heading(): string
    {
        return match ($this->research->status) {
            ResearchStatus::RESEARCH_REGISTERED => 'Research Registered',
            ResearchStatus::RESEARCH_ACCEPTED => 'Research Accepted',
            default => 'Research Approved',
        };
    }

    protected function bodyText(): string
    {
        return match ($this->research->status) {
            ResearchStatus::RESEARCH_REGISTERED => 'Your research has been registered by OVPRI. You may update completion and outcomes when ready.',
            ResearchStatus::RESEARCH_ACCEPTED => 'Congratulations! Your research has been accepted by OVPRI.',
            default => 'Congratulations! Your research has been approved by OVPRI.',
        };
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
        return 'emails.research-approved-faculty';
    }
}
