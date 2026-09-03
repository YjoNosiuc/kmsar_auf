<?php

namespace App\Notifications;

use App\Models\Research;
use App\Support\ResearchNotificationCopy;

class ResearchSubmissionConfirmed extends QueuedResearchNotification
{
    use SendsResearchNotificationMail;

    public function __construct(
        public Research $research
    ) {}

    public function toArray(object $notifiable): array
    {
        return $this->baseResearchPayload($this->research, [
            'message' => ResearchNotificationCopy::submissionConfirmed($this->research),
            'action_url' => route('research.show', $this->research),
            'type' => 'submission_confirmed',
        ]);
    }
}
