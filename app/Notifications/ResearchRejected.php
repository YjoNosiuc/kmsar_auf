<?php

namespace App\Notifications;

use App\Models\Research;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ResearchRejected extends Notification
{
    use Queueable;
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

        return [
            'research_id' => $this->research->id,
            'reference_number' => $this->research->reference_number,
            'title' => $this->research->title,
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
        ];
    }
}
