<?php

namespace App\Notifications;

use App\Models\Research;
use App\Support\ResearchNotificationCopy;

class ResearchProgressUpdated extends QueuedResearchNotification
{
    use SendsResearchNotificationMail;

    public function __construct(
        public Research $research
    ) {}

    public function toArray(object $notifiable): array
    {
        return $this->baseResearchPayload($this->research, [
            'message' => ResearchNotificationCopy::completionSubmittedToDean($this->research),
            'action_url' => route('approval.review', $this->research),
            'type' => 'completion_submitted',
        ]);
    }
}
