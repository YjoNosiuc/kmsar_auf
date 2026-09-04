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
        $research = $this->research;
        $college = $research->motherCollege;
        $collegeLabel = $college !== null && filled($college->code)
            ? $college->name.' ('.$college->code.')'
            : $this->collegeName();

        $cycle = \App\Support\ResearchStatus::reviewCycle($research->status);

        if ($cycle === \App\Support\ResearchStatus::REVIEW_CYCLE_FINAL) {
            return 'The dean of '.$collegeLabel.' has endorsed a research outcome submission for your final OVPRI/CDAIC review. Faculty: '.$this->authorName().'.';
        }

        return 'The dean of '.$collegeLabel.' has endorsed a research registration for your initial OVPRI/CDAIC review. Faculty: '.$this->authorName().'.';
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
