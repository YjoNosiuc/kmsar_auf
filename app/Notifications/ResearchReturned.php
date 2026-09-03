<?php

namespace App\Notifications;

use App\Models\Research;
use App\Support\ResearchNotificationCopy;

class ResearchReturned extends QueuedResearchNotification
{
    use SendsResearchNotificationMail;

    public function __construct(
        public Research $research,
        public ?string $remarks = null,
        public string $returnedBy = 'dean',
    ) {}

    public function toArray(object $notifiable): array
    {
        return $this->baseResearchPayload($this->research, [
            'message' => ResearchNotificationCopy::returnedFaculty(
                $this->research,
                $this->remarks,
                $this->returnedBy,
            ),
            'remarks' => trim((string) $this->remarks) !== '' ? trim((string) $this->remarks) : null,
            'returned_by' => $this->returnedBy,
            'action_url' => route('research.show', $this->research),
            'type' => 'returned',
        ]);
    }
}
