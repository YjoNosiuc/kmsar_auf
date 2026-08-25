<?php

namespace App\Notifications;

use App\Models\Research;

class ResearchRejected extends QueuedResearchNotification
{
    use SendsResearchNotificationMail;

    public function __construct(
        public Research $research,
        public string $remarks,
        public string $rejectedBy = 'dean',
    ) {}


    public function toArray(object $notifiable): array
    {
        $rejectorLabel = $this->rejectedBy === 'ovpri'
            ? 'OVPRI'
            : 'your college dean';

        return $this->baseResearchPayload($this->research, [
            'message' => 'Your research '
                .$this->research->reference_number
                .' has been rejected by '
                .$rejectorLabel
                .'. Remarks: '
                .$this->remarks,
            'remarks' => $this->remarks,
            'rejected_by' => $this->rejectedBy,
            'action_url' => route('research.show', $this->research),
            'type' => 'rejected',
        ]);
    }
}
