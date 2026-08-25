<?php

namespace App\Notifications;

use App\Models\Research;

class ResearchSubmitted extends QueuedResearchNotification
{
    use SendsResearchNotificationMail;

    public function __construct(
        public Research $research
    ) {}


    public function toArray(object $notifiable): array
    {
        return $this->baseResearchPayload($this->research, [
            'message'          => 'A new research '
                                  . $this->research->reference_number
                                  . ' has been submitted for your review by '
                                  . ($this->research->primaryAuthor?->first_name
                                     ?? $this->research->primaryAuthor?->name
                                     ?? 'a faculty member')
                                  . '.',
            'action_url'       => route('approval.review',
                                    $this->research),
            'type'             => 'submitted',
        ]);
    }
}
