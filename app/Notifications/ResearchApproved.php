<?php

namespace App\Notifications;

use App\Models\Research;

class ResearchApproved extends QueuedResearchNotification
{
    use SendsResearchNotificationMail;

    public function __construct(
        public Research $research
    ) {}


    public function toArray(object $notifiable): array
    {
        return $this->baseResearchPayload($this->research, [
            'message'          => 'Your research '
                                  . $this->research->reference_number
                                  . ' has been approved by OVPRI.',
            'action_url'       => route('research.show',
                                    $this->research),
            'type'             => 'approved',
        ]);
    }
}
