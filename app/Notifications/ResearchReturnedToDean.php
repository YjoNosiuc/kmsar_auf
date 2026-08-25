<?php

namespace App\Notifications;

use App\Models\Research;

class ResearchReturnedToDean extends QueuedResearchNotification
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
                                  . ' has been returned by OVPRI '
                                  . 'for your review and action.',
            'action_url'       => route('approval.review',
                                    $this->research),
            'type'             => 'returned_to_dean',
        ]);
    }
}
