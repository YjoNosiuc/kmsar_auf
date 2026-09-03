<?php

namespace App\Notifications;

use App\Models\Research;
use App\Support\ResearchNotificationCopy;

/**
 * Sent to OVPRI/CDAIC admins when a dean endorses research.
 */
class ResearchEndorsedToOvpri extends QueuedResearchNotification
{
    use SendsResearchNotificationMail;

    public function __construct(
        public Research $research
    ) {}

    public function toArray(object $notifiable): array
    {
        return $this->baseResearchPayload($this->research, [
            'message' => ResearchNotificationCopy::endorsedToOvpri($this->research),
            'action_url' => route('ovpri.review', $this->research),
            'type' => 'endorsed_to_ovpri',
        ]);
    }
}
