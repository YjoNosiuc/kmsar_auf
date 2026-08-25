<?php

namespace App\Notifications;

use App\Models\Research;

/**
 * Sent to OVPRI/CDAIC admins when a dean endorses research (forwarded for final review).
 */
class ResearchPendingOvpriReview extends QueuedResearchNotification
{
    use SendsResearchNotificationMail;

    public function __construct(
        public Research $research
    ) {}


    public function toArray(object $notifiable): array
    {
        return $this->baseResearchPayload($this->research, [
            'message'          => 'Research '
                .$this->research->reference_number
                .' has been endorsed by the college dean and awaits OVPRI/CDAIC review.',
            'action_url'       => route('ovpri.review', $this->research),
            'type'             => 'ovpri_pending',
        ]);
    }
}
