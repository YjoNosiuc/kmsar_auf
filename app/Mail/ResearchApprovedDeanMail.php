<?php

namespace App\Mail;

use App\Models\Research;
use App\Models\User;
use App\Support\ResearchStatus;

class ResearchApprovedDeanMail extends ResearchNotificationMail
{
    public function __construct(Research $research, public User $dean)
    {
        parent::__construct($research);
    }

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
        $faculty = $this->authorName();

        return match ($this->research->status) {
            ResearchStatus::RESEARCH_REGISTERED => 'Research from your college has been registered by OVPRI. Faculty: '.$faculty.'.',
            ResearchStatus::RESEARCH_ACCEPTED => 'Research from your college has been accepted by OVPRI. Faculty: '.$faculty.'.',
            default => 'Research from your college has been approved by OVPRI. Faculty: '.$faculty.'.',
        };
    }

    protected function actionUrl(): ?string
    {
        return route('approval.review', $this->research);
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
