<?php

namespace App\Notifications;

use App\Models\Research;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ResearchApproved extends Notification
{
    use Queueable;

    public function __construct(
        public Research $research
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'research_id'      => $this->research->id,
            'reference_number' => $this->research->reference_number,
            'title'            => $this->research->title,
            'message'          => 'Your research '
                                  . $this->research->reference_number
                                  . ' has been approved by OVPRI.',
            'action_url'       => route('research.show',
                                    $this->research),
            'type'             => 'approved',
        ];
    }
}
