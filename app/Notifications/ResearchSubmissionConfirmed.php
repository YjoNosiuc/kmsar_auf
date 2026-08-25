<?php

namespace App\Notifications;

use App\Models\Research;

class ResearchSubmissionConfirmed extends QueuedResearchNotification
{
    use SendsResearchNotificationMail;

    public function __construct(
        public Research $research
    ) {}


    public function toArray(object $notifiable): array
    {
        return $this->baseResearchPayload($this->research, [
            'message' => 'Your research '
                .$this->research->reference_number
                .' has been submitted for dean review.',
            'action_url' => route('research.show', $this->research),
            'type' => 'submission_confirmed',
        ]);
    }
}
