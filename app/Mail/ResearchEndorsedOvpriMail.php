<?php

namespace App\Mail;

use App\Models\Research;
use App\Models\User;

class ResearchEndorsedOvpriMail extends ResearchNotificationMail
{
    public function __construct(Research $research, public User $ovpri)
    {
        parent::__construct($research);
    }

    protected function subjectLine(): string
    {
        return 'New Research Pending OVPRI Review — '.$this->titleSnippet();
    }

    protected function heading(): string
    {
        return 'New Research Pending OVPRI Review';
    }

    protected function bodyText(): string
    {
        return 'A research has been endorsed by the Dean and is now pending your review. College: '.$this->collegeName().'. Faculty: '.$this->authorName().'.';
    }

    protected function actionUrl(): ?string
    {
        return route('ovpri.review', $this->research);
    }

    protected function actionLabel(): string
    {
        return 'Review Research';
    }

    protected function recipientName(): string
    {
        return $this->ovpri->name ?? 'OVPRI';
    }

    protected function emailView(): string
    {
        return 'emails.research-endorsed-ovpri';
    }
}
