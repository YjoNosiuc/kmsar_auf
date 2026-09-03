<?php

namespace App\Notifications;

use App\Models\Research;
use App\Support\ResearchNotificationCopy;

class ResearchReturnedToDean extends QueuedResearchNotification
{
    use SendsResearchNotificationMail;

    public function __construct(
        public Research $research,
        public ?string $remarks = null,
    ) {}

    public function toArray(object $notifiable): array
    {
        return $this->baseResearchPayload($this->research, [
            'message' => ResearchNotificationCopy::returnedToDean($this->research, $this->remarks),
            'remarks' => trim((string) $this->remarks) !== '' ? trim((string) $this->remarks) : null,
            'action_url' => route('approval.review', $this->research),
            'type' => 'returned_to_dean',
        ]);
    }
}
