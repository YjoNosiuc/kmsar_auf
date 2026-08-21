<?php

namespace App\Mail;

use App\Models\Research;
use App\Models\User;

class ResearchSubmittedDeanMail extends ResearchNotificationMail
{
    public function __construct(Research $research, public User $dean)
    {
        parent::__construct($research);
    }

    protected function subjectLine(): string
    {
        return 'New Research Pending Endorsement — '.$this->titleSnippet();
    }

    protected function heading(): string
    {
        return 'New Research Pending Endorsement';
    }

    protected function bodyText(): string
    {
        return 'A new research submission requires your endorsement. Faculty: '.$this->authorName().'. College: '.$this->collegeName().'.';
    }

    protected function actionUrl(): ?string
    {
        return route('approval.review', $this->research);
    }

    protected function actionLabel(): string
    {
        return 'Review Research';
    }

    protected function recipientName(): string
    {
        return $this->dean->name ?? 'Dean';
    }

    protected function emailView(): string
    {
        return 'emails.research-submitted-dean';
    }
}
