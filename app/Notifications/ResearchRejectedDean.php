<?php

namespace App\Notifications;

use App\Models\Research;

class ResearchRejectedDean extends QueuedResearchNotification
{
    use SendsResearchNotificationMail;

    public function __construct(
        public Research $research
    ) {}


    public function toArray(object $notifiable): array
    {
        return $this->baseResearchPayload($this->research, [
            'message'          => 'Research '
                                  . $this->research->reference_number
                                  . ' from your college has been '
                                  . 'rejected by OVPRI.',
            'action_url'       => route('approval.review',
                                    $this->research),
            'type'             => 'rejected',
        ]);
    }
}
